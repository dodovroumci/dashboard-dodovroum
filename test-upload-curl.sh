#!/bin/bash

# Script de test pour upload d'image vers NestJS
# Usage: ./test-upload-curl.sh <chemin_vers_image> <token_jwt>

IMAGE_PATH="$1"
TOKEN="$2"
API_URL="${3:-http://127.0.0.1:3000/api/upload/single}"

if [ -z "$IMAGE_PATH" ] || [ -z "$TOKEN" ]; then
    echo "Usage: $0 <chemin_vers_image> <token_jwt> [api_url]"
    echo "Exemple: $0 /tmp/test.jpg eyJhbGciOiJIUzI1NiIs..."
    exit 1
fi

if [ ! -f "$IMAGE_PATH" ]; then
    echo "Erreur: Le fichier $IMAGE_PATH n'existe pas"
    exit 1
fi

echo "📤 Test d'upload vers NestJS..."
echo "   Image: $IMAGE_PATH"
echo "   URL: $API_URL"
echo "   Token: ${TOKEN:0:20}..."
echo ""

# Envoyer la requête
curl -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F "file=@$IMAGE_PATH" \
  -F "category=residences" \
  -v

echo ""
echo ""
echo "✅ Test terminé. Vérifiez la réponse ci-dessus."

