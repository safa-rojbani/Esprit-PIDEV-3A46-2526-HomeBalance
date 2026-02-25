# Documentation - Resume IA des documents (Hugging Face)

## 1. Objectif metier
Ajouter un resume automatique des documents dans le module Documents.

Resultat:
- l'utilisateur ouvre un document,
- clique sur `Resumer AI`,
- l'application extrait le texte du fichier,
- envoie le texte a Hugging Face,
- affiche un resume directement dans l'interface.

## 2. Architecture implementee

### Backend
- Controller API: `src/Controller/ModuleDocuments/FrontOffice/API/DocumentSummarizeController.php`
- Service client IA: `src/Service/HuggingFaceSummarizerClient.php`
- Service extraction texte: `src/Service/DocumentTextExtractor.php`

### Frontend
- Vue document: `templates/ModuleDocuments/FrontOffice/document/show.html.twig`
- Ajout:
  - bouton `Resumer AI`
  - carte de resume
  - appel AJAX vers l'endpoint de resume

### Configuration
- `config/services.yaml`
  - parametre `app.huggingface.base_uri`
  - definition service `HuggingFaceSummarizerClient`
  - definition service `DocumentTextExtractor`
- `.env`
  - `HUGGINGFACE_API_KEY`
  - `HUGGINGFACE_SUMMARY_MODEL`

## 3. Endpoint API

### Route
- Nom: `app_document_summarize`
- Methode: `POST`
- URL: `/portal/documents/{id}/summarize`

### Securite
- Controle de session utilisateur.
- Controle d'appartenance de famille (`ActiveFamilyResolver` + verification family document).

### Parametres optionnels (JSON ou form-data)
- `min_length` (defaut: `40`)
- `max_length` (defaut: `140`)

Contraintes:
- `max_length` entre `30` et `400`
- `min_length` >= `5` et `< max_length`

### Reponse succes (200)
```json
{
  "ok": true,
  "document": {
    "id": 17,
    "name": "contrat.pdf",
    "type": "application/pdf"
  },
  "summary": "Resume genere...",
  "meta": {
    "model": "facebook/bart-large-cnn",
    "input_length": 7821,
    "input_truncated": false,
    "text_source": "cloudconvert_txt",
    "input_format": "pdf",
    "was_converted": true,
    "max_length": 140,
    "min_length": 40
  }
}
```

### Reponses d'erreur
- `400`: parametres invalides / texte vide
- `403`: acces refuse (famille differente)
- `502`: erreur provider (CloudConvert ou Hugging Face)
- `503`: `HUGGINGFACE_API_KEY` non configure

## 4. Extraction du texte (strategie)

`DocumentTextExtractor` fonctionne en 2 modes:

1. **Lecture locale directe** (`local_text`)
   - pour fichiers deja textuels: `text/*`, `txt`, `md`, `csv`, `json`, `xml`, `log`.

2. **Conversion CloudConvert en TXT** (`cloudconvert_txt`)
   - pour formats binaires (ex: PDF, DOCX, etc.).
   - conversion vers `txt`, puis telechargement du texte converti.

Le texte est ensuite normalise (suppression BOM, trim, normalisation des retours ligne).

## 5. Appel Hugging Face

Le service `HuggingFaceSummarizerClient`:
- envoie `POST` vers `https://router.huggingface.co/hf-inference/models/{model}`
- headers:
  - `Authorization: Bearer <HUGGINGFACE_API_KEY>`
  - `Content-Type: application/json`
- payload:
  - `inputs`: texte document
  - `parameters`: `max_length`, `min_length`, `do_sample=false`
  - `options.wait_for_model=true`

Protection de taille:
- le texte est nettoye et tronque a 12000 caracteres max avant envoi.

## 6. Integration UI

Dans `show.html.twig`:
- bouton toolbar `Resumer AI`
- action menu `Resumer AI`
- carte `hbSummaryCard` avec:
  - `min_length`
  - `max_length`
  - bouton `Generer le resume`
  - bloc resultat

JS:
- `hbInitDocumentSummary()`
- appel `fetch` POST vers `app_document_summarize`
- affichage du resume + meta (modele, source texte, conversion, longueur)

## 7. Configuration requise

Dans `.env.local` (recommande):
```env
HUGGINGFACE_API_KEY=hf_xxxxxxxxxxxxxxxxx
HUGGINGFACE_SUMMARY_MODEL=facebook/bart-large-cnn
```

Optionnel: changer de modele selon la langue et vos besoins.

## 8. Procedure de test

1. Configurer `HUGGINGFACE_API_KEY`.
2. Ouvrir un document (`/portal/documents/{id}`).
3. Cliquer `Resumer AI`.
4. Garder `min_length=40`, `max_length=140`.
5. Cliquer `Generer le resume`.

Verification attendue:
- message "Resume genere avec succes."
- resume visible dans la carte.

## 9. Depannage

### `HUGGINGFACE_API_KEY is not configured`
- definir la variable dans `.env.local`
- relancer/rafraichir l'application

### `Access denied`
- verifier que l'utilisateur courant appartient a la meme famille que le document

### `Unsupported document format for text extraction`
- document non textuel sans conversion possible
- verifier `CLOUDCONVERT_API_KEY` si fichier PDF/DOCX

### `Unable to extract summary from Hugging Face response`
- modele non compatible summarization
- tester un autre modele summarization

## 10. Limites actuelles

- Pas de cache des resumes (chaque clic relance IA).
- Pas de persistance en base du resume.
- Qualite dependante du modele choisi et de la langue du document.

## 11. Evolutions recommandees

- stocker le resume en base (versionning par document/mise a jour).
- ajouter historique des resumes.
- ajouter choix du modele par admin.
- ajouter extraction OCR pour documents scannes.
