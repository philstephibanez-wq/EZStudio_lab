from module import (
    ModuleConfig,
    ModuleContext,
    file_sha256,
    release_accelerator_memory,
    run_module,
)

# Deprecated compatibility aliases.
GeneSpec = ModuleConfig
GeneContext = ModuleContext
run_gene = run_module
