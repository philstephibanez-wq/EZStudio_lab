from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PROFILE = ROOT / "python/ezstudio/pipeline/profile"
RUNTIME = ROOT / "python/ezstudio/runtime"


def read(path: Path) -> str:
    if not path.is_file():
        raise RuntimeError(f"required_file_missing:{path}")
    return path.read_text(encoding="utf-8")


def write(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8", newline="\n")


# 1. gene.py -> module.py
gene = PROFILE / "gene.py"
text = read(gene)
text = text.replace("from perf_probe import GenePerformanceProbe",
                    "from perf_probe import ModulePerformanceProbe")
text = text.replace("Callable[[GeneContext]", "Callable[[ModuleContext]")
text = text.replace("GenePerformanceProbe(", "ModulePerformanceProbe(")
text = text.replace("gene_id=config.module_id", "module_id=config.module_id")
text = text.replace("gene_name=config.display_name", "module_name=config.display_name")
text = re.sub(
    r"\n# Legacy aliases retained for terminology-migration compatibility\.\n"
    r"GeneSpec = ModuleConfig\nGeneContext = ModuleContext\nrun_gene = run_module\n?",
    "\n",
    text,
)
write(PROFILE / "module.py", text)

write(
    gene,
    "from module import (\n"
    "    ModuleConfig,\n"
    "    ModuleContext,\n"
    "    file_sha256,\n"
    "    release_accelerator_memory,\n"
    "    run_module,\n"
    ")\n\n"
    "# Deprecated compatibility aliases.\n"
    "GeneSpec = ModuleConfig\n"
    "GeneContext = ModuleContext\n"
    "run_gene = run_module\n"
)

# 2. perf probe terminology
perf = PROFILE / "perf_probe.py"
text = read(perf)
text = text.replace("class GenePerformanceProbe:", "class ModulePerformanceProbe:")
text = text.replace("gene_id: str", "module_id: str")
text = text.replace("gene_name: str", "module_name: str")
text = text.replace("self.gene_id = gene_id", "self.module_id = module_id")
text = text.replace("self.gene_name = gene_name", "self.module_name = module_name")
text = text.replace("self.gene_id", "self.module_id")
text = text.replace("self.gene_name", "self.module_name")
text = text.replace("profile-gene.v1", "profile-module.v1")
text = text.replace('"included_in_adn": False', '"included_in_preset": False')
text = text.replace('"gene": {', '"module": {')
text += "\n\n# Deprecated compatibility alias.\nGenePerformanceProbe = ModulePerformanceProbe\n"
write(perf, text)

# 3. genes/ -> modules/
old_pkg = PROFILE / "genes"
new_pkg = PROFILE / "modules"
if new_pkg.exists():
    shutil.rmtree(new_pkg)
shutil.copytree(old_pkg, new_pkg)

for p in new_pkg.glob("*.py"):
    text = read(p)
    text = text.replace("from gene import ", "from module import ")
    text = text.replace("ezstudio.runtime.gene_spec", "ezstudio.runtime.module_config")
    text = text.replace('"gene_spec"', '"module_config"')
    text = text.replace('"gene_spec_revision"', '"module_config_revision"')
    text = text.replace('"gene_spec_sha256"', '"module_config_sha256"')
    write(p, text)

for p in old_pkg.glob("*.py"):
    name = p.stem
    if name == "__init__":
        write(p, "# Deprecated compatibility package. Active modules live in profile/modules.\n")
    else:
        write(p, f"from modules.{name} import *  # noqa: F401,F403\n")

# 4. gene_registry.py -> module_registry.py
src = PROFILE / "gene_registry.py"
text = read(src)
text = text.replace("from gene import ", "from module import ")
text = text.replace("def genome_manifest(", "def preset_manifest(")
text = text.replace("genes = []", "modules = []")
text = text.replace("genes.append(", "modules.append(")
text = text.replace('"schema": "ezstudio.genome.region.v1"', '"schema": "ezstudio.preset.phase.v1"')
text = text.replace('"region": "profile"', '"phase": "profile"')
text = text.replace('"genes": genes', '"modules": modules')
text = text.replace('"schema": "ezstudio.profile.genes.v1"', '"schema": "ezstudio.profile.modules.v1"')
text = text.replace('"genes": records', '"modules": records')
text = re.sub(r"\bgene_id\b", "module_id", text)
text = re.sub(r"\bseen_gene_label\b", "seen_module_label", text)
text = re.sub(r"\bdirect_genes\b", "direct_modules", text)
text = text.replace('"gene": module_id', '"module": module_id')
text = text.replace('"gene": record["id"]', '"module": record["id"]')
text = text.replace('item["gene"]', 'item["module"]')
text = text.replace('item.get("gene")', 'item.get("module")')
text = text.replace('row["gene"]', 'row["module"]')
text = text.replace('row.get("gene")', 'row.get("module")')
text = text.replace('source["gene"]', 'source["module"]')
text = text.replace('source.get("gene")', 'source.get("module")')
text = text.replace("No calibrated cross-gene tonal probability yet;",
                    "No calibrated cross-module tonal probability yet;")
text = re.sub(r"\bgene\b", "module", text)
text = re.sub(r"\bgenes\b", "modules", text)
text = re.sub(r"\bGene\b", "Module", text)
text = re.sub(r"\bGenes\b", "Modules", text)
text = re.sub(
    r"\n# Legacy alias retained for terminology-migration compatibility\.\n"
    r"GeneRegistry = ModuleRegistry\n?",
    "\n",
    text,
)
write(PROFILE / "module_registry.py", text)
write(
    src,
    "from module_registry import ModuleRegistry\n\n"
    "# Deprecated compatibility alias.\n"
    "GeneRegistry = ModuleRegistry\n"
)

# 5. runtime/gene_spec.py -> module_config.py
src = RUNTIME / "gene_spec.py"
text = read(src)
text = text.replace('self.data["gene"]["id"]', 'self.data["module"]["id"]')
text = text.replace('self.data["gene"]["revision"]', 'self.data["module"]["revision"]')
text = text.replace('self.data["gene"]["region"]', 'self.data["module"]["phase"]')
text = text.replace('"gene_id": self.gene_id', '"module_id": self.module_id')
text = text.replace('"gene_revision": self.revision', '"module_revision": self.revision')
text = text.replace('"region": self.region', '"phase": self.phase')
text = text.replace('"spec_path":', '"config_path":')
text = text.replace('"spec_sha256":', '"config_sha256":')
text = text.replace('schema != "ezstudio.gene-spec.v1"', 'schema != "ezstudio.module-config.v1"')
text = text.replace('"unsupported_gene_spec_schema"', '"unsupported_module_config_schema"')
text = text.replace('data.get("gene")', 'data.get("module")')
text = text.replace('"gene"', '"module"')
text = text.replace('"region"', '"phase"')
text = text.replace("gene.region_invalid", "module.phase_invalid")
text = text.replace("gene_spec_missing", "module_config_missing")
text = text.replace("gene_spec_invalid_json", "module_config_invalid_json")
text = text.replace("gene_spec_root_must_be_object", "module_config_root_must_be_object")
text = re.sub(
    r"\n    @property\n    def gene_id\(self\) -> str:\n        return self\.module_id\n",
    "\n",
    text,
)
text = re.sub(
    r"\n    @property\n    def region\(self\) -> str:\n        return self\.phase\n",
    "\n",
    text,
)
text = re.sub(
    r"\n# Legacy API aliases; persisted schema remains ezstudio\.gene-spec\.v1 in R1\.\n"
    r"GeneSpecError = ModuleConfigError\n"
    r"GeneSpecDocument = ModuleConfigDocument\n"
    r"validate_gene_spec = validate_module_config\n"
    r"load_gene_spec = load_module_config\n?",
    "\n",
    text,
)
write(RUNTIME / "module_config.py", text)
write(
    src,
    "from .module_config import (\n"
    "    ModuleConfigDocument,\n"
    "    ModuleConfigError,\n"
    "    load_module_config,\n"
    "    validate_module_config,\n"
    ")\n\n"
    "# Deprecated compatibility aliases.\n"
    "GeneSpecError = ModuleConfigError\n"
    "GeneSpecDocument = ModuleConfigDocument\n"
    "validate_gene_spec = validate_module_config\n"
    "load_gene_spec = load_module_config\n"
)

init = RUNTIME / "__init__.py"
text = read(init)
text = text.replace("declarative gene runtime", "declarative module runtime")
text = text.replace("region-agnostic", "phase-agnostic")
text = text.replace(
    "from .gene_spec import ModuleConfigDocument, GeneSpecError, load_module_config",
    "from .module_config import ModuleConfigDocument, ModuleConfigError, load_module_config",
)
text = text.replace('"GeneSpecError"', '"ModuleConfigError"')
write(init, text)

adapter = RUNTIME / "adapters/clap_zero_shot.py"
text = read(adapter)
text = text.replace("ezstudio.runtime.gene_spec", "ezstudio.runtime.module_config")
text = text.replace("clap_gene_spec_model_required", "clap_module_config_model_required")
text = text.replace("clap_gene_spec_parameters_invalid", "clap_module_config_parameters_invalid")
text = text.replace('"gene_spec": spec.provenance()', '"module_config": spec.provenance()')
write(adapter, text)

# 6. canonical preset path/config
old_cfg = ROOT / "adn/specs/profile/semantic.clap-open-vocabulary.r3b35c.json"
if not old_cfg.is_file():
    raise RuntimeError("legacy_profile_config_missing")
cfg_text = read(old_cfg)
cfg_text = cfg_text.replace('"schema": "ezstudio.gene-spec.v1"', '"schema": "ezstudio.module-config.v1"')
cfg_text = cfg_text.replace('"gene": {', '"module": {')
cfg_text = cfg_text.replace('"region": "profile"', '"phase": "profile"')
new_cfg = ROOT / "presets/profile/semantic.clap-open-vocabulary.r3b35c.json"
write(new_cfg, cfg_text)
old_cfg.unlink()

for d in [ROOT/"adn/specs/profile", ROOT/"adn/specs", ROOT/"adn"]:
    try:
        d.rmdir()
    except OSError:
        pass

clap = PROFILE / "modules/semantic_clap_open_vocab.py"
text = read(clap)
text = text.replace(
    'SPEC_RELATIVE_PATH = Path(\n    "adn/specs/profile/semantic.clap-open-vocabulary.r3b35c.json"\n)',
    'CONFIG_RELATIVE_PATH = Path(\n    "presets/profile/semantic.clap-open-vocabulary.r3b35c.json"\n)',
)
text = text.replace("def _spec_path()", "def _config_path()")
text = text.replace("SPEC_RELATIVE_PATH", "CONFIG_RELATIVE_PATH")
text = text.replace("load_module_config(_spec_path())", "load_module_config(_config_path())")
write(clap, text)

# 7. runner canonical contract
runner = PROFILE / "runner.py"
text = read(runner)
text = text.replace("from gene import ModuleContext, ModuleConfig", "from module import ModuleContext, ModuleConfig")
text = text.replace("from gene_registry import ModuleRegistry", "from module_registry import ModuleRegistry")
text = text.replace("from genes import ", "from modules import ")
text = text.replace("R3B15_PROFILE_GENES", "R3B15_PROFILE_MODULES")
text = text.replace("R3B31_GENOMIC_EVOLUTION", "R3B31_PRESET_EVOLUTION")
text = text.replace("def _gene_by_id(", "def _module_by_id(")
text = text.replace("gene_id: str", "module_id: str")
text = text.replace('module_registry_result.get("genes")', 'module_registry_result.get("modules")')
text = text.replace("record.get(\"id\") == gene_id", 'record.get("id") == module_id')
text = text.replace("_gene_by_id(", "_module_by_id(")
text = text.replace("independent gene outputs", "independent module outputs")
text = text.replace("profile_gene_error:", "profile_module_error:")
text = text.replace("gene_rows", "module_rows")
text = text.replace('"genes": module_rows', '"modules": module_rows')
text = text.replace("genome_manifest = module_registry.genome_manifest(", "preset_manifest = module_registry.preset_manifest(")
text = text.replace('"gene_registry": {', '"module_registry": {')
text = text.replace('"genome": genome_manifest', '"preset": preset_manifest')
text = text.replace('"profile_architecture": "genes-r3b35c-canonical-contract"',
                    '"profile_architecture": "modules-r3b35c-canonical-contract"')
text = text.replace('write_progress(progress, 96, "PROFILE: écriture ADN R3B31")',
                    'write_progress(progress, 96, "PROFILE: écriture Preset R3B31")')
text = re.sub(
    r"\n# Legacy callable alias during terminology migration\.\n"
    r"build_gene_registry = build_module_registry\n",
    "\n",
    text,
)
write(runner, text)

# 8. canonical_profile source evidence key
cp = PROFILE / "canonical_profile.py"
text = read(cp)
text = re.sub(r"\bdirect_genes\b", "direct_modules", text)
text = re.sub(r"\bgene\b", "module", text)
text = re.sub(r"\bgenes\b", "modules", text)
write(cp, text)

# 9. PHP/Twig consumers
p = ROOT / "src/Service/PresetRegistry.php"
text = read(p)
text = text.replace("ezstudio.genome.phase.v1", "ezstudio.preset.phase.v1")
write(p, text)

p = ROOT / "src/Service/ProfileDnaExecutionService.php"
if p.is_file():
    text = read(p)
    text = text.replace("$result['gene_registry']", "$result['module_registry']")
    text = text.replace("$result['genome']", "$result['preset']")
    write(p, text)

p = ROOT / "templates/workbench/profile.html.twig"
if p.is_file():
    text = read(p)
    text = text.replace("pv.genes", "pv.modules")
    text = text.replace("{% set genes =", "{% set modules =")
    text = text.replace("{% if genes %}", "{% if modules %}")
    text = text.replace("{% for e in genes %}", "{% for e in modules %}")
    text = text.replace("legacy_gene_key", "legacy_module_key")
    text = text.replace("s.gene", "s.module")
    text = text.replace("e.gene", "e.module")
    text = text.replace("saved.gene_revision_ref", "saved.module_revision_ref")
    text = text.replace("saved.region_revision_ref", "saved.phase_revision_ref")
    text = text.replace("subject.scope == 'gene' ? 'Module' : 'Phase'",
                        "subject.scope == 'module' ? 'Module' : 'Phase'")
    write(p, text)

# 10. Historical contract tests updated, scientific assertions preserved
t = ROOT / "scripts/test_profile_r3b35c_contract.py"
text = read(t)
text = text.replace('gene_registry.py"', 'module_registry.py"')
text = text.replace('{"gene": "semantic.panns-cnn14"}', '{"module": "semantic.panns-cnn14"}')
text = text.replace('{"gene": "semantic.passt-audioset"}', '{"module": "semantic.passt-audioset"}')
write(t, text)

t = ROOT / "scripts/test_declarative_runtime_v1a.py"
text = read(t)
text = text.replace('ROOT / "adn" / "specs" / "profile"', 'ROOT / "presets" / "profile"')
text = text.replace("from ezstudio.runtime.gene_spec import load_gene_spec",
                    "from ezstudio.runtime.module_config import load_module_config")
text = text.replace("load_gene_spec(SPEC)", "load_module_config(SPEC)")
text = text.replace("doc.gene_id", "doc.module_id")
text = text.replace("doc.region", "doc.phase")
text = text.replace('spec["gene"]["id"]', 'spec["module"]["id"]')
text = text.replace("Gene Spec", "Module Config")
write(t, text)

t = ROOT / "scripts/test_declarative_runtime_v1b.py"
text = read(t)
text = text.replace("from ezstudio.runtime.gene_spec import load_gene_spec",
                    "from ezstudio.runtime.module_config import load_module_config")
text = text.replace('ROOT / "adn/specs/profile/', 'ROOT / "presets/profile/')
text = text.replace('profile/genes/semantic_clap_open_vocab.py',
                    'profile/modules/semantic_clap_open_vocab.py')
text = text.replace("load_gene_spec(SPEC)", "load_module_config(SPEC)")
text = text.replace("spec.region", "spec.phase")
text = text.replace("spec.gene_id", "spec.module_id")
text = text.replace("Gene Spec", "Module Config")
write(t, text)

print("TERMINOLOGY_PRESET_R2B_CODE_APPLIED")
print("Active PROFILE contract now uses Module / Phase / Preset terminology")
print("Scientific settings and model configuration unchanged")
