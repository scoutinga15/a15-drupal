# Clubhouse Booking

De `clubhouse_booking` module is een aangepaste Drupal-oplossing voor het verhuren van het clubhuis van Scouting A15. Deze module biedt een kalenderinterface voor zowel beheerders als gasten, waarmee vrije slots kunnen worden beheerd en boekingen kunnen worden aangevraagd.

## Functionaliteiten

### Voor Gasten
- **Interactieve Kalender**: Een FullCalendar-weergave waarop vrije en geboekte periodes zichtbaar zijn.
- **Boekingen**: Gasten kunnen direct vrije slots boeken of een aangepaste periode aanvragen via een formulier.
- **Annuleren**: Gasten kunnen hun boeking annuleren en ontvangen hiervan een bevestigingsmail.
- **Vrije Slots Lijst**: Een overzichtelijk blok (Paragraph) dat alle toekomstige vrije slots gegroepeerd per maand toont.

### Voor Beheerders
- **Slotbeheer**: Beheerders kunnen vrije slots aanmaken, bewerken en verwijderen via een tabeloverzicht in de backend.
- **Goedkeuringsproces**: Aanvragen kunnen worden beoordeeld, goedgekeurd of aangepast door een beheerder.
- **Statusbeheer**: Boekingen hebben verschillende statussen: `requested` (aangevraagd), `reserved` (gereserveerd), of `booked` (geboekt).
- **Notificaties**: Beheerders ontvangen alle boekingsverzoeken en goedkeuringsbevestigingen op `verhuur@scoutinga15.nl`.
- **Backend Filter**: Een tabel met alle slots, standaard gesorteerd op datum (oplopend) en gefilterd op geboekte slots, met uitgebreide filteropties.

## Installatie

1. Schakel de module in via de Drupal backend of Drush:
   ```bash
   drush en clubhouse_booking
   ```
2. De module vereist de `datetime`, `datetime_range` en `paragraphs` modules.
3. Configureer de gewenste Paragraph types (`clubhouse_booking` en `clubhouse_booking_free_slot_list`) op de inhoudstypes waar je de boekingsfunctionaliteit wilt gebruiken.

## Gebruik

### Paragraph Types
De module levert twee Paragraph types:
1. **Clubhouse Booking**: Toont de FullCalendar-interface waar gebruikers datums kunnen selecteren en boekingen kunnen aanvragen.
2. **Clubhouse Booking Free Slot List**: Toont een lijst met toekomstige vrije slots, gegroepeerd per maand.

### Beheer (Backend)
Beheerders kunnen de boekingen en slots beheren via het menu onder **Inhoud > Clubhouse Booking** (indien geconfigureerd in `links.menu.yml`).
Hier kunnen slots worden toegevoegd (Add Slot) of bestaande slots worden gewijzigd/verwijderd.

## Technische Details
- **FullCalendar**: Gebruikt voor de front-end weergave van de kalender (`js/booking_calendar.js`).
- **Vertalingen**: De module is volledig vertaald naar het Nederlands via de meegeleverde `.po` bestanden in de `translations` map.
- **E-mail**: Integratie voor het verzenden van bevestigingen en notificaties aan beheerders.

## Ontwikkeling
De CSS en JS bestanden bevinden zich in de respectievelijke `css/` en `js/` mappen. Templates voor de Paragraphs en de kalender zijn te vinden in de `templates/` map.
