from .module_config import (
    ModuleConfigDocument,
    ModuleConfigError,
    load_module_config,
    validate_module_config,
)

# Deprecated compatibility aliases.
GeneSpecError = ModuleConfigError
GeneSpecDocument = ModuleConfigDocument
validate_gene_spec = validate_module_config
load_gene_spec = load_module_config
