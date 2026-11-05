"""Fail-closed Windows Job Object wrapper for the unchanged LAB worker_entrypoint.

This file is a PROCESS ADAPTER, not a scientific engine. It always starts
worker_entrypoint.py with precisely its original --job-file contract.
"""
from __future__ import annotations

import argparse
import ctypes
import os
import subprocess
import sys
import time
from ctypes import wintypes
from pathlib import Path


class LARGE_INTEGER(ctypes.Structure):
    _fields_ = [("QuadPart", ctypes.c_longlong)]


class JOBOBJECT_BASIC_LIMIT_INFORMATION(ctypes.Structure):
    _fields_ = [
        ("PerProcessUserTimeLimit", LARGE_INTEGER),
        ("PerJobUserTimeLimit", LARGE_INTEGER),
        ("LimitFlags", wintypes.DWORD),
        ("MinimumWorkingSetSize", ctypes.c_size_t),
        ("MaximumWorkingSetSize", ctypes.c_size_t),
        ("ActiveProcessLimit", wintypes.DWORD),
        ("Affinity", ctypes.c_size_t),
        ("PriorityClass", wintypes.DWORD),
        ("SchedulingClass", wintypes.DWORD),
    ]


class IO_COUNTERS(ctypes.Structure):
    _fields_ = [
        ("ReadOperationCount", ctypes.c_ulonglong),
        ("WriteOperationCount", ctypes.c_ulonglong),
        ("OtherOperationCount", ctypes.c_ulonglong),
        ("ReadTransferCount", ctypes.c_ulonglong),
        ("WriteTransferCount", ctypes.c_ulonglong),
        ("OtherTransferCount", ctypes.c_ulonglong),
    ]


class JOBOBJECT_EXTENDED_LIMIT_INFORMATION(ctypes.Structure):
    _fields_ = [
        ("BasicLimitInformation", JOBOBJECT_BASIC_LIMIT_INFORMATION),
        ("IoInfo", IO_COUNTERS),
        ("ProcessMemoryLimit", ctypes.c_size_t),
        ("JobMemoryLimit", ctypes.c_size_t),
        ("PeakProcessMemoryUsed", ctypes.c_size_t),
        ("PeakJobMemoryUsed", ctypes.c_size_t),
    ]


class JOBOBJECT_BASIC_ACCOUNTING_INFORMATION(ctypes.Structure):
    _fields_ = [
        ("TotalUserTime", LARGE_INTEGER),
        ("TotalKernelTime", LARGE_INTEGER),
        ("ThisPeriodTotalUserTime", LARGE_INTEGER),
        ("ThisPeriodTotalKernelTime", LARGE_INTEGER),
        ("TotalPageFaultCount", wintypes.DWORD),
        ("TotalProcesses", wintypes.DWORD),
        ("ActiveProcesses", wintypes.DWORD),
        ("TotalTerminatedProcesses", wintypes.DWORD),
    ]


class STARTUPINFOW(ctypes.Structure):
    _fields_ = [
        ("cb", wintypes.DWORD),
        ("lpReserved", wintypes.LPWSTR),
        ("lpDesktop", wintypes.LPWSTR),
        ("lpTitle", wintypes.LPWSTR),
        ("dwX", wintypes.DWORD),
        ("dwY", wintypes.DWORD),
        ("dwXSize", wintypes.DWORD),
        ("dwYSize", wintypes.DWORD),
        ("dwXCountChars", wintypes.DWORD),
        ("dwYCountChars", wintypes.DWORD),
        ("dwFillAttribute", wintypes.DWORD),
        ("dwFlags", wintypes.DWORD),
        ("wShowWindow", wintypes.WORD),
        ("cbReserved2", wintypes.WORD),
        ("lpReserved2", ctypes.POINTER(wintypes.BYTE)),
        ("hStdInput", wintypes.HANDLE),
        ("hStdOutput", wintypes.HANDLE),
        ("hStdError", wintypes.HANDLE),
    ]


class PROCESS_INFORMATION(ctypes.Structure):
    _fields_ = [
        ("hProcess", wintypes.HANDLE),
        ("hThread", wintypes.HANDLE),
        ("dwProcessId", wintypes.DWORD),
        ("dwThreadId", wintypes.DWORD),
    ]


def _windows_job_run(worker: Path, job_file: Path) -> int:
    import msvcrt  # Windows only; imported after platform check

    kernel32 = ctypes.WinDLL("kernel32", use_last_error=True)

    kernel32.CreateJobObjectW.argtypes = [ctypes.c_void_p, wintypes.LPCWSTR]
    kernel32.CreateJobObjectW.restype = wintypes.HANDLE
    kernel32.SetInformationJobObject.argtypes = [wintypes.HANDLE, ctypes.c_int, ctypes.c_void_p, wintypes.DWORD]
    kernel32.SetInformationJobObject.restype = wintypes.BOOL
    kernel32.AssignProcessToJobObject.argtypes = [wintypes.HANDLE, wintypes.HANDLE]
    kernel32.AssignProcessToJobObject.restype = wintypes.BOOL
    kernel32.CreateProcessW.argtypes = [
        wintypes.LPCWSTR, wintypes.LPWSTR, ctypes.c_void_p, ctypes.c_void_p,
        wintypes.BOOL, wintypes.DWORD, ctypes.c_void_p, wintypes.LPCWSTR,
        ctypes.POINTER(STARTUPINFOW), ctypes.POINTER(PROCESS_INFORMATION),
    ]
    kernel32.CreateProcessW.restype = wintypes.BOOL
    kernel32.ResumeThread.argtypes = [wintypes.HANDLE]
    kernel32.ResumeThread.restype = wintypes.DWORD
    kernel32.WaitForSingleObject.argtypes = [wintypes.HANDLE, wintypes.DWORD]
    kernel32.WaitForSingleObject.restype = wintypes.DWORD
    kernel32.GetExitCodeProcess.argtypes = [wintypes.HANDLE, ctypes.POINTER(wintypes.DWORD)]
    kernel32.GetExitCodeProcess.restype = wintypes.BOOL
    kernel32.QueryInformationJobObject.argtypes = [wintypes.HANDLE, ctypes.c_int, ctypes.c_void_p,
                                                    wintypes.DWORD, ctypes.c_void_p]
    kernel32.QueryInformationJobObject.restype = wintypes.BOOL
    kernel32.TerminateProcess.argtypes = [wintypes.HANDLE, wintypes.UINT]
    kernel32.TerminateProcess.restype = wintypes.BOOL
    kernel32.CloseHandle.argtypes = [wintypes.HANDLE]
    kernel32.CloseHandle.restype = wintypes.BOOL

    def checked(result: object, operation: str) -> None:
        if not result:
            raise OSError(ctypes.get_last_error(), "jobobject_" + operation)

    job = kernel32.CreateJobObjectW(None, None)
    checked(job, "create")
    proc = PROCESS_INFORMATION()
    created = False
    assigned = False
    root_handle_open = False
    try:
        limits = JOBOBJECT_EXTENDED_LIMIT_INFORMATION()
        limits.BasicLimitInformation.LimitFlags = 0x00002000  # KILL_ON_JOB_CLOSE
        checked(kernel32.SetInformationJobObject(job, 9, ctypes.byref(limits), ctypes.sizeof(limits)),
                "kill_on_close")
        startup = STARTUPINFOW()
        startup.cb = ctypes.sizeof(startup)
        startup.dwFlags = 0x00000100  # STARTF_USESTDHANDLES
        startup.hStdInput = wintypes.HANDLE(msvcrt.get_osfhandle(sys.stdin.fileno()))
        startup.hStdOutput = wintypes.HANDLE(msvcrt.get_osfhandle(sys.stdout.fileno()))
        startup.hStdError = wintypes.HANDLE(msvcrt.get_osfhandle(sys.stderr.fileno()))
        cmdline = subprocess.list2cmdline([sys.executable, str(worker), "--job-file", str(job_file)])
        cmd_buffer = ctypes.create_unicode_buffer(cmdline)
        checked(kernel32.CreateProcessW(
            sys.executable, cmd_buffer, None, None, True,
            0x00000004 | 0x08000000,  # CREATE_SUSPENDED | CREATE_NO_WINDOW
            None, str(worker.parent.parent), ctypes.byref(startup), ctypes.byref(proc),
        ), "spawn_suspended")
        created = True
        root_handle_open = True
        checked(kernel32.AssignProcessToJobObject(job, proc.hProcess), "assign_before_resume")
        assigned = True
        if kernel32.ResumeThread(proc.hThread) == 0xFFFFFFFF:
            raise OSError(ctypes.get_last_error(), "jobobject_resume_failed")
        if kernel32.WaitForSingleObject(proc.hProcess, 0xFFFFFFFF) != 0:
            raise RuntimeError("jobobject_root_wait_failed")
        code = wintypes.DWORD(0)
        checked(kernel32.GetExitCodeProcess(proc.hProcess, ctypes.byref(code)), "exit_code")
        if code.value != 0:
            return min(255, int(code.value))
        # A Job Object is NOT signaled when its processes exit normally.
        # Close the finished root process handle before checking accounting;
        # a retained process-object reference can delay ActiveProcesses reaching 0.
        kernel32.CloseHandle(proc.hProcess)
        root_handle_open = False
        deadline = time.monotonic() + 30.0
        while True:
            info = JOBOBJECT_BASIC_ACCOUNTING_INFORMATION()
            checked(kernel32.QueryInformationJobObject(job, 1, ctypes.byref(info), ctypes.sizeof(info), None),
                    "verify_no_descendants")
            if info.ActiveProcesses == 0:
                return 0
            if time.monotonic() >= deadline:
                raise RuntimeError("jobobject_descendants_still_running")
            time.sleep(0.1)
    finally:
        if created and not assigned:
            kernel32.TerminateProcess(proc.hProcess, 1)
        if created:
            kernel32.CloseHandle(proc.hThread)
            if root_handle_open:
                kernel32.CloseHandle(proc.hProcess)
        # Closing the job handle kills descendants if the root died prematurely.
        kernel32.CloseHandle(job)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--worker", required=True)
    parser.add_argument("--job-file", required=True)
    args = parser.parse_args()
    if os.name != "nt":
        raise RuntimeError("windows_jobobject_required")
    lab_root = Path(__file__).resolve().parents[1]
    expected = lab_root / "analysis" / "worker_entrypoint.py"
    worker = Path(args.worker).resolve()
    if worker != expected.resolve() or not worker.is_file():
        raise RuntimeError("canonical_worker_path_required")
    job_file = Path(args.job_file).resolve()
    if not job_file.is_file():
        raise RuntimeError("job_file_missing")
    return _windows_job_run(worker, job_file)


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(f"EZS_PROFILE_JOB_OBJECT_FAILED:{type(error).__name__}:{error}", file=sys.stderr, flush=True)
        sys.exit(97)
