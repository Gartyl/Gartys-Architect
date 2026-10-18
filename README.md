<div align="center">
  <img src="banner.png" alt="Garty's Architect AI Studio" width="100%">
</div>
<br>

# 🏛️ Garty's Architect — Integrated Local AI Studio

[![Platform](https://img.shields.io/badge/Platform-Windows%2010%2F11%20(x64)-0078D6?style=for-the-badge&logo=windows)](https://github.com/Gartyl/Gartys-Architect/releases)
[![Engine](https://img.shields.io/badge/Powered%20By-FrankenPHP%20%2B%20SQLite3-777BB4?style=for-the-badge&logo=php)](https://frankenphp.dev/)
[![AI Cores](https://img.shields.io/badge/AI%20Cores-Ollama%20%2B%20ComfyUI-FF6F00?style=for-the-badge)](https://github.com/Gartyl/Gartys-Architect/wiki)
[![License](https://img.shields.io/badge/Edition-Free%20%26%20PRO-FFC107?style=for-the-badge)](https://github.com/Gartyl/Gartys-Architect)

<p align="center">
  <b>🌐 Language / Idioma:</b> &nbsp; 🇬🇧 <b>English</b> &nbsp;|&nbsp; <a href="README.es.md">🇪🇸 <b>Español</b></a>
</p>

Welcome to the official repository for **Garty's Architect**, your ultimate local desktop command center for orchestrating generative AI models. Built to bridge the gap between the raw, limitless power of **ComfyUI + Ollama** and the clean, focused experience of a professional creative studio.

**Garty's Architect** is distributed as a **100% standalone, portable application** powered by an embedded **FrankenPHP** server and **SQLite3**. No Python environment conflicts, no Apache/XAMPP setups, and no Docker containers required. Just launch and create.

> ⚠️ **Important Note:** Garty's Architect is an advanced orchestration framework and UI. It does **not** distribute, host, or bundle any AI models or checkpoints. You retain full control over your own locally hosted models.

---

## ⚡ Core Features

* 💬 **Unified Multimodal Super-Chat & RAG:** Control ComfyUI and Ollama from a single responsive dark-mode interface. Analyze PDFs/DOCX, perform high-precision OCR on images, query real-time internet data via the built-in **Sniper RAG Engine** (Wikipedia API + DuckDuckGo Lite), and trigger GPU renders directly using slash commands (`/img`, `/gpu`). Say goodbye to spaghetti nodes!
* 🤖 **The Auto-Architect (1-Click Semantic Routing):** Not sure which checkpoint, resolution, or CFG to use? Type your idea in plain language and hit the Magic Wand. Your local LLM analyzes your request, reads the semantic tags of your installed models, selects the best engine, adjusts the aspect ratio, writes the technical prompt, and fires the GPU automatically.
* 🎬 **Next-Gen Cinematic Video Studio:** Native orchestration for **Wan 2.1/2.2, LTX-Video, Hunyuan Video 2.1, and MiniMax H3** (including automatic `.safetensors` / `.gguf` switching, dynamic LoRA bypassing, and Multi-Tray reference injection of up to 9 images in a single shot).
* 🔊 **Pro Audio, Voice Cloning & Foley Studio:** Clone voices with **MiniMax H3 (20B)** (featuring ultra-fast audio-only decoding and automatic FFmpeg silence/sample trimming), **OmniVoice** (Dual-Mode: Zero-Shot Voice Design + Reference Cloning), **IndexTTS-2**, and **F5-TTS**. Generate video-synced sound effects with **HunyuanVideo-Foley**, music with **Stable Audio**, and craft acoustic prompts with the **AI Music Maestro**.
* 🧠 **Semantic Vector History & YAML Wildcards:** Every prompt and chat response is automatically indexed using local embeddings (`nomic-embed-text`), letting you search your history by *meaning and concept*, not just keywords. Supports inline `{a|b|c}` dynamic syntax and `.yaml` wildcard trees.
* 🔋 **Smart VRAM Arbitrator & Safe Model Manager:** Configure per-model `keep_alive` policies so Ollama instantly flushes VRAM (`0s`) before ComfyUI starts heavy rendering. Includes a built-in Civitai Downloader, an OTA GitHub Auto-Updater that protects user files, and safe physical deletion (sending obsolete ComfyUI models to the **Windows Recycle Bin**).
* 🌍 **Multilingual & 100% Private:** Native support for **English, Spanish, and Catalan** (with a live in-app translation editor). Your data, your hardware, your rules.

---

## 💻 Hardware Requirements

Since Garty's Architect communicates directly with your local AI instances, performance depends entirely on your hardware:

* **Minimum Setup (SD 1.5 / SDXL / 8B LLMs):**
  * **CPU:** Modern quad-core processor.
  * **GPU:** NVIDIA GPU with **8 GB VRAM**.
  * **RAM:** 16 GB system memory.
* **Recommended Setup (Flux / DiT / Video Generation / 14B–27B LLMs):**
  * **CPU:** Modern multi-core processor (e.g., Intel Core i7-14700KF / AMD Ryzen 7 or higher).
  * **GPU:** NVIDIA RTX series with **16 GB+ VRAM** (e.g., RTX 4060 Ti 16GB, RTX 5060 16GB, RTX 4080, RTX 3090/4090).
  * **RAM:** 32 GB+ system memory.

---

## 🧠 Prerequisites 1: The Logic Core (Ollama)

Garty's Architect relies on local LLMs to act as the "brains" behind the scenes (prompt engineering, optical analysis, audio direction, and semantic indexing). Ensure you have **[Ollama](https://ollama.com)** running locally on port `11434`.

Because Garty's Architect is **100% model-agnostic**, you can register any model you prefer in the Admin Panel. For the best out-of-the-box experience, we recommend pulling these baseline roles:

1. **`SYS_LLM` (The Prompt Engineer):** `llama3.1:8b` *(or `gemma3:12b` / `deepseek-r1:14b`)*
   * Works silently in the background to structure prompts, power the Auto-Architect, and write acoustic scores for the AI Music Maestro.
2. **`SYS_VISION` (The Shadow Optical Analyst):** `granite3.2-vision`
   * A fast vision model used internally to extract structural tags for tools like IP-Adapter and ControlNet.
3. **Multimodal Chat Model:** `qwen2.5-vl:7b` *(or `gemma3:27b`)*
   * Used in the Super-Chat for deep conversational analysis, coding, and 100% faithful OCR document transcription.
4. **Semantic Search Engine (Automatic):** `nomic-embed-text:latest`
   * Ultra-lightweight model (274 MB) used to index your prompt history for conceptual vector searches.

### ⚡ Quick Terminal Install (Baseline Kit)
Open your terminal (`CMD` or `PowerShell`) and run:

```powershell
ollama pull llama3.1:8b
ollama pull granite3.2-vision
ollama pull qwen2.5-vl:7b
ollama pull nomic-embed-text:latest
```

*(Note: New users can also install the baseline LLM and Embeddings with **1-Click** directly from the in-app Onboarding screen!)*

---

## 🎨 Prerequisites 2: The Render Core (ComfyUI & FFmpeg)

Ensure you have **ComfyUI** running locally on port `8188` and **FFmpeg** installed on your system `PATH` (required for video merging and automatic voice sample trimming).

To unlock all **PRO Studio** workflows, install the following Custom Nodes via **ComfyUI Manager**:

* **Video & Audio Packaging:** `ComfyUI-VideoHelperSuite` *(Mandatory for MP4/Audio merging)*
* **Quantized Model Loader (.gguf):** `ComfyUI-GGUF`
* **Face Swapping:** `ComfyUI-ReActor`
* **Style & Composition Transfer:** `ComfyUI_IPAdapter_plus`
* **Structure Control (Canny/Depth/DWPose):** `ComfyUI-Advanced-ControlNet` & `comfyui_controlnet_aux`
* **Face Repair (ADetailer) & Inpainting:** `ComfyUI-Impact-Pack`
* **High-Res Tiled Upscaling:** `ComfyUI_UltimateSDUpscale`
* **Background Removal:** `ComfyUI-Rembg`
* **Image Relighting & Colorization:** `ComfyUI-IC-Light` & `ComfyUI-DDColor`
* **Advanced Image Editing:** `ComfyUI-Flux2KontextConditioner` & `ComfyUI-Qwen-Image-Edit`
* **Voice Cloning & TTS Engines:** `ComfyUI-MiniMax-H3`, `ComfyUI-OmniVoice`, `ComfyUI-IndexTTS`, `ComfyUI-F5-TTS`
* **Video Foley & Lip-Sync:** `ComfyUI-HunyuanVideo-Foley` & `ComfyUI-Wav2Lip`

---

## 🛠️ Quick Start & Installation

Getting started takes less than a minute:

1. Download the latest **Garty's Architect `.zip`** from the **[Releases](../../releases)** tab.
2. Extract the contents into a non-protected folder on your drive (e.g., `C:\GartysArchitect\`). *Avoid `C:\Program Files\` to ensure write permissions for SQLite and gallery files.*
3. ⚙️ **CRITICAL STEP — Configure `config.php`:**
   * Locate the file named **`config.sample.php`** in the root folder and rename it to **`config.php`**.
   * Open `config.php` with any text editor and set the absolute path to your ComfyUI `models` directory:
     ```php
     define('COMFY_MODELS_DIR', 'C:/ComfyUI/models');
     ```
   * *(Optional)* Paste your **Civitai API Key** into `define('API_KEY', '...');` to enable the built-in model downloader.
4. Make sure **Ollama** (`11434`) and **ComfyUI** (`8188`) are running in the background.
5. Double-click **`GartysArchitect.exe`**. A console window will boot the internal FrankenPHP engine and **automatically launch your browser** at `http://localhost:8000` (keep the console window open while using the app).
6. Complete the **Smart Onboarding** check (and optionally enter your **★ PRO License Key** in the top bar to unlock all studio modules).

---

## 📖 Official Documentation & Advanced Wiki

Ditch the outdated PDFs! Every single feature—from setting up custom Chat Personalities with dynamic JSON temperatures, configuring Unbundled DiT/UNET architectures, mastering the 4 Voice Cloning engines, to VRAM optimization and Troubleshooting—is thoroughly documented in our official Wiki:

👉 **[Read the Full Technical Manual in the Official Wiki](../../wiki)**

---

## 🚀 Editions: Free vs. PRO

### 1. The Core Experience (Free)
Perfect for getting started with local AI orchestration:
* Unified Multimodal Super-Chat, Sniper RAG web search, Document/OCR analysis, and Prompt Architect.
* Standard Image Generation for classic architectures (**SD 1.5 & SDXL**).
* 1-Click Onboarding, SQLite semantic history, Dynamic Prompts/Wildcards, and Multilingual UI.
* 👉 **[Download the latest `.zip` from Releases](../../releases)**

### 2. ★ PRO Studio License
Built for power creators and filmmakers. Unlocks the full arsenal of Garty's Architect:
* **Heavyweight & Unbundled Architectures:** Full support for **Flux, Flux 2 Klein, Chroma, Z-Image, Anima, SD 3.5, Krea-2, Qwen-Image, Qwen-Edit, Hunyuan Image, HiDream, and Ideogram**.
* **Cinematic Video Generation:** Unlocks **Wan 2.1/2.2, LTX-Video, Hunyuan Video 2.1, and MiniMax H3** (with Multi-Image Reference injection and GGUF support).
* **Full Audio & Voice Cloning Suite:** Unlocks **MiniMax H3 (20B) Voice Clone, OmniVoice Dual-Mode, IndexTTS-2, F5-TTS, HunyuanVideo-Foley, Stable Audio, AI Music Maestro, and Wav2Lip**.
* **Advanced Graphic Studio:** Unlocks **ControlNet Studio, IP-Adapter (Multi-Tray), ReActor (FaceSwap), ADetailer, Inpainting/Outpainting Canvas, IC-Light, DDColor, Rembg, and Tiled Upscaling**.
* **Custom AI Personas & Video Seeds:** Create and edit custom Chat Personalities and Video motion presets.
* 👉 **[Unlock the PRO License here](YOUR_LEMON_SQUEEZY_LINK_HERE)**

---

## 🔮 The Roadmap

Garty's Architect is an actively developed Swiss Army knife for local AI enthusiasts. Coming soon:
* **Continuous Engine Evolution:** Day-one integration of upcoming open-weights image, video, and audio architectures.
* **The Server Edition:** A dedicated multi-user release designed for studio networks, featuring MySQL/MariaDB backends, granular role-based permissions, shared galleries, and audit logs.

---

## 🐛 Bug Reports & Feature Requests

Have an idea for a new feature or found a bug?
Please open a ticket in the **[Issues](../../issues)** tab. Before reporting a rendering failure, please verify in the [Troubleshooting Wiki](../../wiki) that your ComfyUI Custom Nodes, `COMFY_MODELS_DIR` path, and Ollama models are properly installed.
