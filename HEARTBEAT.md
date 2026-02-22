# HEARTBEAT.md

# Proactieve routine (lichtgewicht)

Bij een heartbeat:
1. Check `memory/heartbeat-state.json` (maak aan als die ontbreekt).
2. Doe alleen checks die niet recent zijn gedaan.

Checks en intervallen:
- Email: elke 4 uur
- Calendar (volgende 48u): elke 4 uur
- Weather (alleen relevant bij agenda buitenshuis): elke 8 uur
- Open acties/todo's in recente memory-bestanden: elke 8 uur

Regels:
- Tussen 23:00–08:00 (Europe/Amsterdam) alleen alarmeren bij urgentie.
- Als er niets nieuws/urgents is: antwoord exact `HEARTBEAT_OK`.
- Als er wel iets belangrijks is: stuur 1 korte, concrete update met actievoorstel.
- Geen dubbele meldingen over hetzelfde item binnen 6 uur.
