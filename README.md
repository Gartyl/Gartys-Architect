# 🏢 Garty's Architect - Integrated AI Studio

Welcome to the official repository for **Garty's Architect**, your ultimate local desktop interface for orchestrating AI models. Built to bridge the gap between the raw, limitless power of ComfyUI/Ollama and the clean, focused experience of a professional design studio.

Garty's Architect is distributed as a standalone, portable application powered by FrankenPHP. No complex web server installations, Docker containers, or environment configurations required. Just launch and create.

## ⚡ Core Features
* **Unified Dashboard:** Control ComfyUI and Ollama from a clean, responsive dark-mode interface. Say goodbye to spaghetti nodes and terminal windows.
* **Zero-Configuration Portable Executable:** Runs locally out of a single folder using an embedded FrankenPHP binary.
* **Deterministic Asynchronous Generation:** Fire off your prompts and close the tab. The system processes everything in the background and safely stores the images with zero duplicates.
* **Lightweight SQLite Storage:** Every prompt, seed, model, and metadata is automatically saved to a local, zero-config SQLite database.
* **Multilingual UI:** Native support for English, Spanish, and Catalan.
* **100% Local & Private:** Your data, your hardware, your rules.

## 💻 Hardware Requirements
Since Garty's Architect communicates directly with your local AI instances, performance depends entirely on your machine.

**Minimum Setup (SDXL / Basic Workflows):**
* CPU: Any modern quad-core processor.
* GPU: NVIDIA GPU with 8GB VRAM.
* RAM: 16GB system memory.

**Recommended Setup (Flux / Advanced Video Generation):**
* CPU: Modern multi-core processor (e.g., Intel Core i7-14700KF or equivalent).
* GPU: NVIDIA RTX series with 16GB VRAM (e.g., RTX 5060 or higher).
* RAM: 32GB system memory.

## 🚀 How to Get It
Garty's Architect is distributed in two versions.

### 1. Community Edition (Free)
Perfect for getting started. Includes the core unified dashboard, asynchronous generation, zero-config SQLite history, and multilingual support.
👉 **[Download the Community Edition .zip directly from the Releases tab on the right.](https://github.com/Gartyl/Gartys-Architect/releases)**

### 2. PRO License
For power users. Unlocks the true potential of the integrated studio:
* Advanced Workflows: Access to LTX-Video/Wan generation.
* Face Swapping & Image Intel: Native ReActor integration and advanced image analysis.
* Unlimited Prompt Engineering Tools.
👉 **[Unlock the PRO License here](https://garty.lemonsqueezy.com)**

## 🔮 The Roadmap: Where we are heading
This project is actively in development, and I am committed to constant improvement. Here is a sneak peek of what’s coming in future updates:

* **Expanded Modalities:** Native integration for Audio generation and upcoming cutting-edge video workflows (like Hunyuan Video).
* **The Server Edition:** A future standalone release designed for remote network access, complete with secure multi-user logins (MySQL/MariaDB), role management, and audit logs.

## 🛠️ Quick Start & Installation
Getting started takes less than a minute:

1. Download the Community Edition .zip from the Releases tab (or your PRO version from Lemon Squeezy).
2. Unzip the contents into any folder on your local machine.
3. ⚙️ **IMPORTANT:** Open the `config.php` file with any text editor (like Notepad). Set the correct absolute path to your ComfyUI models folder and add your Civitai API key if you want to use the integrated downloader.
4. Run `GartysArchitect.exe` (this will automatically initialize the local environment).
5. Ensure you have ComfyUI and Ollama running locally in the background.
6. Open your browser and navigate to `http://localhost:8000` if it doesn't open automatically.
7. **(PRO Users)** Enter your License Key in the UI panel to unlock advanced features.

## 🐛 Bug Reports & Feature Requests
Have an idea for a new feature? Found a bug?
Please use the **Issues** tab at the top of this repository to let me know.

---
*Designed and coded by Garty.*
