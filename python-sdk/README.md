# 🐍 DPA-E: Python SDK (The Mini War Room)

### 🧠 Lightweight. Sovereign. Programmable.
> "The Python implementation of DPA-E is the raw neural path of our architecture—minimalist and unbreakable." — *Daraima Bassey*

This folder contains the **Standalone Python Implementation** of the DPA-E (Execution) engine. It is a single-file Flask microservice designed for developers who need a portable "War Room" without the overhead of a CMS.

---

## 🚀 Key Features
* **Single-Call Dialectics:** Injects the DPA-I System Soul into a single API payload for maximum token efficiency.
* **Gemini 2.0 Optimized:** Specifically tuned for the `generateContent` schema of Gemini 2.0 Flash.
* **Resilient Parsing:** Includes the `safe_parse_dpa_array` heuristic to extract JSON even if the model wraps output in Markdown.
* **Industrial Networking:** Built-in exponential backoff and adaptive header handling (API Key vs. OAuth).

---

## 🛠️ Installation & Setup

### 1. Requirements
Ensure you have Python 3.10+ installed.

```bash
pip install flask pydantic requests
