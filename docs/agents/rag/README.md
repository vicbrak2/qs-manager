# Datasets RAG

Esta carpeta guarda datasets versionados para el chatbot RAG.

Estructura recomendada:

- `public/`: contenido seguro para clientas y web publica.
- `internal/`: reglas operativas internas. No mezclar con el bot publico.
- `benchmark/`: competencia y analisis comparativo. No mezclar con el bot publico.

Convenciones:

- Un archivo JSON por dataset.
- Formato actual compatible con el uploader de WordPress:
  - `title`
  - `url`
  - `content`
- Mientras el pipeline no guarde metadata estructurada en Qdrant, incluir `Visibilidad`, `Intent` y `Tags` dentro de `content`.

Dataset inicial:

- `public/qamiluna-rag-publico.json`
