# Analysis Engine V2A — UML / architecture

```mermaid
classDiagram
    class ChainPreset {
      +id
      +revision
      +phases[]
    }
    class PhasePreset {
      +id
      +revision
      +phase
      +modules[]
    }
    class ModulePreset {
      +id
      +revision
      +adapter
      +engine
      +model
      +settings
      +decision
    }
    class AnalysisEngine {
      +plan()
      +execute()
    }
    class PresetLoader
    class ExecutionPlanner
    class EngineAdapterRegistry
    class EngineAdapter
    class ModelResolver

    ChainPreset "1" o-- "*" PhasePreset
    PhasePreset "1" o-- "*" ModulePreset
    AnalysisEngine --> PresetLoader
    AnalysisEngine --> ExecutionPlanner
    AnalysisEngine --> EngineAdapterRegistry
    ModulePreset --> EngineAdapter
    EngineAdapterRegistry --> EngineAdapter
    ModulePreset --> ModelResolver
```

## Flux

```text
PhasePreset JSON
      │
      ▼
PresetLoader ── schema/contract validation
      │
      ▼
ExecutionPlanner ── ordre déterministe
      │
      ▼
AnalysisEngine
      │
      ├── AdapterRegistry ── adapter technique ── moteur / modèle
      │
      └── Provenance ── hashes + versions + ordre effectif
      │
      ▼
PhaseExecution
```

V2A ne remplace pas encore le runner PROFILE. Il introduit le socle exécutable et
le Preset de compatibilité. V2B effectuera le branchement PROFILE + comparaison
stricte contre le run #78.
