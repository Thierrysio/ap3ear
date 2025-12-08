# Mémo Jeu de Piste

Ce mémo résume le fonctionnement de l'API mobile et du modèle de données ajouté pour le jeu de piste.

## Endpoints
- `GET /api/mobile/jeu/questions` : retourne 5 questions aléatoires avec leurs réponses et les informations minimales du lieu associé.
- `GET /api/mobile/jeu/lieu/{id}` : retourne le nom et l'URL de l'image d'un lieu identifié par son `id`.
- `POST /api/mobile/jeu/valider` : valide un QR Code pour un lieu donné et renvoie un booléen `valide`, le score conservé et un message. Corps attendu : `{ "lieu_id": number, "code_qr": string, "score": number }`.

## Schéma de données
- **Lieu** (`aurelien_lieu`) : `id`, `nom`, `image_url`, `code_qr`.
- **Question** (`aurelien_question`) : `id`, `libelle`, `lieu_id` (FK vers `aurelien_lieu`).
- **Reponse** (`aurelien_reponse`) : `id`, `libelle`, `est_correcte`, `question_id` (FK vers `aurelien_question`).

Les relations sont en cascade sur les suppressions (`Lieu` → `Question` → `Reponse`).
