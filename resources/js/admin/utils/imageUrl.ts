/**
 * Construit l'URL d'affichage des images stockées sur l'API (api.dodovroum.com/storage).
 * À utiliser partout où on affiche une image venue du backend (résidences, véhicules, offres).
 *
 * Règle : en base on stocke de préférence uniquement le chemin relatif ou le nom de fichier,
 * et le frontend ajoute le préfixe VITE_STORAGE_URL.
 */

const STORAGE_URL =
  (typeof import.meta !== 'undefined' && (import.meta as unknown as { env?: { VITE_STORAGE_URL?: string } }).env?.VITE_STORAGE_URL) ||
  'https://api.dodovroum.com/storage';

/**
 * Extrait la première URL d'image exploitable depuis un objet API (résidence, véhicule, offre).
 * Gère imageUrl, tableaux d'objets { url }, etc.
 */
export function pickDisplayableImage(
  source: Record<string, unknown> | null | undefined,
  listKeys: string[] = ['images', 'photos', 'imageUrls', 'galerie']
): string | null {
  if (!source || typeof source !== 'object') return null;

  const scalarKeys = [
    'imageUrl',
    'image_url',
    'coverImage',
    'cover_image',
    'thumbnail',
    'image',
    'photo',
    'picture',
    'mainImage',
  ];

  for (const k of scalarKeys) {
    const v = source[k];
    if (typeof v === 'string' && v.trim()) return v.trim();
  }

  for (const key of listKeys) {
    const arr = source[key];
    if (!Array.isArray(arr) || arr.length === 0) continue;
    const first = arr[0];
    if (typeof first === 'string' && first.trim()) return first.trim();
    if (first && typeof first === 'object') {
      const o = first as Record<string, unknown>;
      const url = o.url ?? o.src ?? o.path ?? o.href;
      if (typeof url === 'string' && url.trim()) return url.trim();
    }
  }

  return null;
}

/**
 * Retourne l'URL complète pour afficher une image.
 * @param image - URL complète (http/https), chemin relatif (/storage/... ou /residences/...), ou nom de fichier
 * @param type - Contexte optionnel : 'residences' | 'vehicles' | 'offers' pour les noms de fichier seuls
 */
export function getStorageImageUrl(
  image: string | null | undefined,
  type?: 'residences' | 'vehicles' | 'offers'
): string {
  if (!image || typeof image !== 'string') return '';

  const trimmed = image.trim();
  if (!trimmed) return '';

  // Correctif : forcer le passage par le sous-domaine API si ancien domaine détecté
  let path = trimmed;
  path = path.replace(/(https?:\/\/)(www\.)?dodovroum\.com/g, '$1api.dodovroum.com');

  // Déjà une URL absolue
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }

  // Chemin absolu côté serveur (ex: /storage/residences/xxx.jpg)
  if (path.startsWith('/')) {
    const apiOrigin = STORAGE_URL.replace(/\/storage\/?$/, '');
    return `${apiOrigin}${path}`;
  }

  const apiOrigin = STORAGE_URL.replace(/\/storage\/?$/, '');
  const lower = path.toLowerCase();

  // Déjà préfixé storage/... (sans slash initial) — évite /storage/residences/storage/...
  if (lower.startsWith('storage/')) {
    return `${apiOrigin}/${path}`;
  }

  // Dossier API explicite residences|vehicles|offers en tête de chemin
  if (/^(residences|vehicles|offers)\//i.test(path)) {
    return `${apiOrigin}/storage/${path}`;
  }

  // Chemin relatif ou nom de fichier : préfixer par le type si fourni
  const segment = type ? `/${type}/` : '/';
  return `${STORAGE_URL.replace(/\/$/, '')}${segment}${path}`;
}
