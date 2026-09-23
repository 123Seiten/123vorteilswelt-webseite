/**
 * Markenkonfiguration — die zentrale Stelle für alle marken-spezifischen
 * Identitäts-Werte dieser Website.
 *
 * Für einen Markenwechsel (Schwestermarke):
 *   1. Diese Datei anpassen (Name, Domain, Claim, E-Mail, Farben).
 *   2. public/logo.png durch das neue Logo ersetzen (gleicher Dateiname
 *      oder logo.src unten anpassen).
 *   3. Die Farbwerte unten MÜSSEN identisch mit den CSS-Custom-Properties
 *      in src/styles/global.css (:root-Block) gepflegt werden — Astro
 *      unterstützt keine dynamische Werte-Injektion in global importierte
 *      Stylesheets, daher sind die Farben hier die dokumentierte
 *      Referenz, die eigentliche CSS-Variable steht in global.css.
 */
export const brand = {
  name: '123Vorteilswelt',
  domain: '123vorteilswelt.at',
  tagline: 'Preise für Profis',
  email: 'office@123vorteilswelt.at',

  logo: {
    /** Relativ zu public/, ohne führenden Slash (Basis-Pfad wird von Logo.astro ergänzt). */
    src: 'logo.png',
  },

  /**
   * Rechtliche Unternehmensdaten für das Impressum (src/pages/impressum.astro).
   * firmenbuchnummer, firmenbuchgericht und uid sind bewusst leer, solange die
   * GmbH-Eintragung noch aussteht — impressum.astro blendet die jeweilige
   * Zeile automatisch aus, bis hier ein Wert steht.
   */
  company: {
    legalName: '123Vorteilswelt Franchise GmbH',
    street: 'Wienersdorfer Straße 20-24/M37/12/1',
    postalCode: '2514',
    city: 'Traiskirchen',
    country: 'Österreich',
    managingDirector: 'Stefan Langmann',
    phone: '+43 660 7693620',
    businessPurpose: 'Optimierungen im gewerblichen Einkauf auf Erfolgsbasis',
    profession: 'Handelsagentur',
    professionCountry: 'Österreich',
    authority: 'Bezirkshauptmannschaft Baden',
    chamber: 'Wirtschaftskammer Niederösterreich',
    firmenbuchnummer: '',
    firmenbuchgericht: '',
    uid: '',
    inquiryEmail: 'anfrage@123vorteilswelt.at',
    franchiseEmail: 'franchise@123vorteilswelt.at',
  },

  /**
   * Referenzwerte — müssen mit src/styles/global.css :root übereinstimmen.
   */
  colors: {
    brand: '#139e1c',
    brandDark: '#0d7714',
    brandTint: '#eaf6ea',
    brandOnDark: '#7fd287',
    savings: '#139e1c',
    savingsOnDark: '#4ade55',
  },
} as const;
