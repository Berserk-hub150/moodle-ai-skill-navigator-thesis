# Architettura iniziale del prototipo

## Obiettivo

Definire una possibile architettura per un AI Tutor integrato in Moodle.

## Architettura concettuale

Moodle
→ AI Tutor
→ Backend/API o provider AI
→ Modello AI
→ Risposta allo studente

## Componenti

### Moodle

Moodle rappresenta la piattaforma didattica in cui sono presenti:

- corsi;
- pagine;
- materiali didattici;
- utenti;
- attività.

### AI Tutor

AI Tutor è il componente che espone allo studente le funzionalità intelligenti.

Possibili forme:

- blocco Moodle;
- plugin Moodle;
- placement AI;
- servizio esterno collegato a Moodle.

### Backend/API

Un eventuale backend può occuparsi di:

- ricevere il testo dal corso;
- costruire il prompt;
- chiamare il servizio AI;
- restituire la risposta;
- applicare regole di sicurezza e logging.

### Servizio AI

Il servizio AI genera:

- riassunti;
- spiegazioni;
- domande;
- risposte contestuali.

## Opzioni tecniche

### Opzione A - Plugin Moodle puro

Il prototipo viene sviluppato come plugin Moodle.

Vantaggi:

- integrazione diretta;
- demo più naturale;
- coerenza con la piattaforma.

Svantaggi:

- maggiore complessità tecnica;
- richiede studio delle API Moodle.

### Opzione B - Moodle + backend esterno

Moodle comunica con un servizio esterno tramite API.

Vantaggi:

- architettura più flessibile;
- separazione tra LMS e logica AI;
- possibile uso di competenze Web API.

Svantaggi:

- integrazione meno nativa;
- richiede progettazione dello scambio dati.

### Opzione C - Studio delle funzioni AI native Moodle

La tesi analizza e configura le funzionalità AI già disponibili in Moodle.

Vantaggi:

- utile per studio comparativo;
- meno rischio tecnico.

Svantaggi:

- contributo sperimentale potenzialmente meno originale.

## Ipotesi preferita

La direzione più forte è una soluzione mista:

Moodle + componente AI Tutor + eventuale backend/API.

In questo modo la tesi contiene:

- analisi dell'integrazione AI in Moodle;
- progettazione architetturale;
- sviluppo prototipale;
- valutazione sperimentale.
