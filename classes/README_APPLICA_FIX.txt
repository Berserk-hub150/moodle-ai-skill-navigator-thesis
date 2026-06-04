AI Skill Navigator - classes fix pack

Sovrascrivi questi file dentro:
C:\Users\Utente\Desktop\TESI-MOODLE\plugins\aiskillnavigator\classes

Fix inclusi:
- observer.php: dedupe automatico dei materiali course_resource dopo sync + pulizia chunk duplicati.
- service/prompt/shared/material_context_builder.php: dedupe materiali prima del prompt.
- service/blueprint/blueprint_material_context.php: dedupe materiali nei blueprint XR.
- service/embedding/rag_context_builder.php: dedupe del contesto RAG.
- service/material_extractor.php: fallback PPTX class-based + fallback DOCX XML.
- service/provider/model_output_cleaner.php: pulizia code fence senza funzioni PHP 8-only.
- service/openai_compatible_ai_provider.php e service/ollama_ai_provider.php: compatibilità PHP senza str_ends_with.
