import json
import os
import urllib.request
import urllib.error
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler


HOST = "0.0.0.0"
PORT = 8000


def build_prompt(action, lesson_title, lesson_text):
    actions = {
        "summary": "Riassumi il contenuto in modo chiaro e sintetico.",
        "simple": "Spiega il contenuto in modo semplice, adatto a uno studente principiante.",
        "questions": "Genera 5 domande di autovalutazione con risposte brevi.",
        "concepts": "Estrai i concetti chiave e spiegali brevemente."
    }

    instruction = actions.get(action, "Aiuta lo studente a comprendere il contenuto.")

    return f"""
Sei un AI Tutor integrato in Moodle.

Obiettivo:
Supportare lo studente nello studio dei materiali del corso.

Titolo della lezione:
{lesson_title}

Contenuto della lezione:
{lesson_text}

Richiesta:
{instruction}

Rispondi in italiano, in modo chiaro, ordinato e utile per lo studio.
"""


def call_openai(prompt):
    api_key = os.environ.get("OPENAI_API_KEY")
    model = os.environ.get("AI_MODEL", "gpt-4o-mini")

    if not api_key:
        return {
            "error": "OPENAI_API_KEY non configurata. Per ottenere risposte AI reali devi impostare una chiave API nel terminale."
        }

    payload = {
        "model": model,
        "input": prompt
    }

    req = urllib.request.Request(
        "https://api.openai.com/v1/responses",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {api_key}"
        },
        method="POST"
    )

    try:
        with urllib.request.urlopen(req, timeout=60) as response:
            data = json.loads(response.read().decode("utf-8"))

        if "output_text" in data:
            return {"text": data["output_text"]}

        texts = []
        for item in data.get("output", []):
            for content in item.get("content", []):
                if content.get("type") in ("output_text", "text"):
                    texts.append(content.get("text", ""))

        if texts:
            return {"text": "\n".join(texts)}

        return {"text": json.dumps(data, indent=2, ensure_ascii=False)}

    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="ignore")
        return {"error": f"Errore API HTTP {e.code}: {body}"}
    except Exception as e:
        return {"error": f"Errore durante la chiamata AI: {str(e)}"}


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

        prompt = build_prompt(action, lesson_title, lesson_text)
        result = call_openai(prompt)

        response = json.dumps(result, ensure_ascii=False).encode("utf-8")

        self.send_response(200)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(response)))
        self.end_headers()
        self.wfile.write(response)


if __name__ == "__main__":
    print(f"AI Tutor backend running on http://{HOST}:{PORT}")
    print("Open: /prototype/ai-tutor-real.html")
    server = ThreadingHTTPServer((HOST, PORT), Handler)
    server.serve_forever()
