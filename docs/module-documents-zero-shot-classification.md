# Documentation - Classification intelligente (Zero-shot)

## Objectif
Classer automatiquement un document texte (ou PDF/DOCX converti en texte) dans des categories metier.

## Fichiers implementes
- `src/Service/HuggingFaceZeroShotClassifierClient.php`
- `src/Controller/ModuleDocuments/FrontOffice/API/DocumentClassifyController.php`
- `templates/ModuleDocuments/FrontOffice/document/show.html.twig`
- `config/services.yaml`

## Endpoint API
- Route: `POST /portal/documents/{id}/classify`
- Nom: `app_document_classify`

## Payload accepte
```json
{
  "labels": ["Administratif", "Facture", "Banque", "Scolaire", "Sante", "Contrat", "Assurance", "Autre"],
  "multi_label": false
}
```

Si `labels` n'est pas fourni, des labels par defaut sont utilises.

## Reponse
```json
{
  "ok": true,
  "classification": {
    "top_label": "Facture",
    "top_score": 0.97,
    "labels": [
      {"label": "Facture", "score": 0.97},
      {"label": "Administratif", "score": 0.02}
    ]
  }
}
```

## Modele utilise
- Variable env: `HUGGINGFACE_ZERO_SHOT_MODEL`
- Valeur par defaut: `facebook/bart-large-mnli`

## Test UI
1. Ouvrir un document.
2. Cliquer `Classifier AI`.
3. Ajuster les categories si besoin.
4. Cliquer `Classer`.
5. Lire la categorie principale et le classement complet.
