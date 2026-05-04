import json
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler


HOST = "0.0.0.0"
PORT = 8000


def generate_demo_response(action, lesson_title, lesson_text):
    if action == "summary":
        return f"""Riassunto del contenuto: {lesson_title}

Questa lezione introduce il concetto di {lesson_title.lower()} e ne evidenzia il ruolo all'interno delle competenze digitali moderne.

Il contenuto può essere usato in Moodle come materiale di studio e può essere supportato da un AI Tutor per aiutare lo studente a comprendere meglio i concetti principali."""

    if action == "simple":
        return f"""Spiegazione semplificata: {lesson_title}

In parole semplici, questa lezione spiega un concetto tecnologico importante.

L'idea centrale è aiutare lo studente a capire non solo la definizione, ma anche perché questo argomento è utile nei percorsi formativi su AI, IoT e Digital Twin."""

    if action == "questions":
        return f"""Domande di autovalutazione su: {lesson_title}

1. Qual è la definizione principale dell'argomento?
2. Perché questo tema è importante nelle digital skills?
3. Quali sono due possibili applicazioni pratiche?
4. In che modo questo argomento può collegarsi ad AI, IoT o Digital Twin?
5. Quali limiti o criticità possono emergere nell'uso di questa tecnologia?

Risposta attesa:
Lo studente dovrebbe dimostrare di aver compreso definizione, applicazioni e collegamenti con il contesto formativo."""

    if action == "concepts":
        return f"""Concetti chiave estratti da: {lesson_title}

- Definizione del concetto.
- Applicazioni pratiche.
- Ruolo nelle competenze digitali.
- Collegamento con AI, IoT e Digital Twin.
- Possibile uso in un ambiente Moodle potenziato dall'AI."""

    return "Azione non riconosciuta."


class Handler(SimpleHTTPRequestHandler):
    def do_POST(self):
        if self.path != "/api/tutor":
            self.send_response(404)
            self.end_headers()
            return

        length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(length).decode("utf-8")
        data = json.loads(body)

        action = data.get("action", "")
        lesson_title = data.get("lessonTitle", "")
        lesson_text = data.get("lessonText", "")

        response_data = {
            "mode": "demo",
            "text": generate_demo_response(action, lesson_title, lesson_text)
        }

        response = json.dumps(response_data, ensure_ascii=False).encode("utf-8")

        self.send_response(200)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(response)))
        self.end_headers()
        self.wfile.write(response)


if __name__ == "__main__":
    print(f"AI Tutor demo backend running on http://{HOST}:{PORT}")
    print("Open: /prototype/ai-tutor-real.html")
    server = ThreadingHTTPServer((HOST, PORT), Handler)
    server.serve_forever()
