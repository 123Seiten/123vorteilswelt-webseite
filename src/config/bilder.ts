/**
 * Bild-Manifest — die zentrale Stelle, an der jedes Bild der Website
 * eingetragen wird: Dateiname, Alt-Text, Quelle und KI-Kennzeichnung.
 *
 * Neues Bild registrieren:
 *   1. Datei ablegen — Inhaltsbild (wird von Astro optimiert) in
 *      src/assets/bilder/, Hintergrundbild (bereits web-optimiert) in
 *      public/bilder/.
 *   2. Hier einen Eintrag ergänzen (Schlüssel frei wählbar, wird beim
 *      Einbinden über <InhaltsBild eintrag="..."> bzw.
 *      <HintergrundBild eintrag="..."> referenziert).
 *
 * Mehr ist nicht nötig: Alt-Text, KI-Hinweis am Bild und die
 * "Bildquellen"-Auflistung im Impressum aktualisieren sich automatisch.
 */

export interface BildEintrag {
  /** Dateiname inkl. Endung, wie im jeweiligen Ordner abgelegt. */
  datei: string;
  /** Alt-Text für Screenreader/SEO. Bei rein dekorativen Bildern: ''. */
  alt: string;
  /** Kurze, menschenlesbare Bezeichnung für die Bildquellen-Auflistung im Impressum. */
  beschreibung: string;
  /** Quelle des Bildmaterials. */
  quelle: string;
  /** true, wenn das Bild (teilweise) KI-generiert ist — zeigt automatisch den Hinweis "mit KI erstellt" am Bild. */
  kiGeneriert: boolean;
}

export const BILDER = {
  'hero-bg': {
    datei: '123_vorteilswelt_die_neue_einkaufsabteilung.jpg',
    alt: '',
    beschreibung: 'Hero-Hintergrundbild Startseite',
    quelle: 'Canva',
    kiGeneriert: false,
  },
  'franchise-hero-bg': {
    datei: 'franchise-hero-bg.jpg',
    alt: '',
    beschreibung: 'Hero-Hintergrund Franchise-Seite (Kundenlager)',
    quelle: 'Canva Pro',
    kiGeneriert: false,
  },
} as const satisfies Record<string, BildEintrag>;

export type BildKey = keyof typeof BILDER;

/** Liefert den Manifest-Eintrag zu einem Schlüssel, mit klarer Fehlermeldung bei Tippfehlern. */
export function getBild(eintrag: BildKey): BildEintrag {
  const bild = BILDER[eintrag];
  if (!bild) {
    throw new Error(`Kein Bild-Manifest-Eintrag für "${eintrag}" in src/config/bilder.ts gefunden.`);
  }
  return bild;
}

/** Bekannte Lizenz-/Rechte-Hinweise pro Quelle für die Bildquellen-Auflistung im Impressum. */
export const BILDQUELLEN_LIZENZ: Record<string, string> = {
  Canva: 'Canva-Inhaltslizenz',
  'Canva Pro': 'Nutzungsrechte Canva Pro',
};
