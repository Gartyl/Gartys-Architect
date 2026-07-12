// ==============================================================================
// --- PRESETS_PERSONALES.JS: GESTIÓN DE PRESETS PROPIOS (API) ---
// ==============================================================================

let personalPresetsData = {};

// 1. Cargar la lista al inicio
async function loadPersonalPresetsList() {
    try {
        const response = await fetch('/modulos/api_presets.php');
        personalPresetsData = await response.json();
        const selector = document.getElementById('personalPresetSelector');
        
        if (selector) {
            selector.innerHTML = `<option value="">${GartyLang.opt_select_preset}</option>`;
            
            for (const [name, config] of Object.entries(personalPresetsData)) {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                selector.appendChild(opt);
            }
        }
    } catch (error) {
        console.error(GartyLang.log_err_load_presets, error);
        const selector = document.getElementById('personalPresetSelector');
        if (selector) selector.innerHTML = `<option value="">${GartyLang.opt_err_load}</option>`;
    }
}

// 2. Guardar preset actual
async function savePersonalPreset() {
    const { value: presetName } = await SwalDark.fire({
        title: `<i class="bi bi-floppy text-success"></i> ${GartyLang.swal_save_preset_title}`,
        text: GartyLang.swal_save_preset_text,
        input: 'text',
        inputPlaceholder: GartyLang.swal_save_preset_ph,
        showCancelButton: true,
        confirmButtonText: GartyLang.btn_guardar,
        cancelButtonText: GartyLang.btn_cancelar,
        inputValidator: (value) => {
            if (!value || value.trim() === "") {
                return GartyLang.swal_save_preset_val;
            }
        }
    });

    if (!presetName) return; // Si cancela el modal

    // Recopilar LoRAs
    const loras = [];
    document.querySelectorAll('#lorasWrapper .row').forEach(row => {
        const sel = row.querySelector('.lora-select') || row.querySelector('.lora-selector');
        const weight = row.querySelector('.lora-weight') || row.querySelector('.lora-strength-high');
        if (sel && weight && sel.value !== "") {
            loras.push({ name: sel.value, weight: weight.value });
        }
    });

    // Crear la "Fotografía" de parámetros
    const config = {
        categoria: document.getElementById('categoriaSelector') ? document.getElementById('categoriaSelector').value : '',
        modelo: document.getElementById('modelSelector') ? document.getElementById('modelSelector').value : '',
        proporcion: document.getElementById('aspectRatio') ? document.getElementById('aspectRatio').value : '1024x1024',
        steps: document.getElementById('stepsInput') ? document.getElementById('stepsInput').value : 30,
        cfg: document.getElementById('cfgInput') ? document.getElementById('cfgInput').value : 5.0,
        sampler: document.getElementById('samplerInput') ? document.getElementById('samplerInput').value : 'euler_ancestral',
        scheduler: document.getElementById('schedulerInput') ? document.getElementById('schedulerInput').value : 'karras',
        loras: loras
    };

    try {
        const response = await fetch('/modulos/api_presets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', name: presetName.trim(), config: config })
        });
        const result = await response.json();
        if (result.status === 'ok') {
            loadPersonalPresetsList(); 
            // Toast (Burbuja) de éxito
            SwalDark.fire({
                title: GartyLang.swal_saved,
                text: `${GartyLang.swal_preset_ready1} '${presetName}' ${GartyLang.swal_preset_ready2}`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    } catch (error) {
        SwalDark.fire({ icon: 'error', title: GartyLang.swal_err_title, text: GartyLang.swal_err_save_preset });
    }
}

// 3. Cargar y aplicar preset
function loadPersonalPreset() {
    const selector = document.getElementById('personalPresetSelector');
    const presetName = selector.value;
    
    if (!presetName || !personalPresetsData[presetName]) {
        SwalDark.fire({
            icon: 'info',
            title: GartyLang.avis_atencio,
            text: GartyLang.swal_preset_must_select,
            confirmButtonText: GartyLang.btn_entendido
        });
        return;
    }

    const config = personalPresetsData[presetName];

    // Aplicar categoría 
    const catSel = document.getElementById('categoriaSelector');
    if (catSel && config.categoria) {
        catSel.value = config.categoria;
        catSel.dispatchEvent(new Event('change')); 
    }

    // Retraso para que pinte modelos y actualice la UI
    setTimeout(() => {
        const modSel = document.getElementById('modelSelector');
        if (modSel && config.modelo) modSel.value = config.modelo;

        if (config.proporcion) document.getElementById('aspectRatio').value = config.proporcion;
        if (config.steps) document.getElementById('stepsInput').value = config.steps;
        if (config.cfg) document.getElementById('cfgInput').value = config.cfg;
        if (config.sampler) document.getElementById('samplerInput').value = config.sampler;
        if (config.scheduler) document.getElementById('schedulerInput').value = config.scheduler;

        const lorasWrapper = document.getElementById('lorasWrapper');
        if (lorasWrapper) {
            lorasWrapper.innerHTML = ''; 
            if (config.loras && config.loras.length > 0) {
                config.loras.forEach(lora => {
                    if(typeof addLoraRow === 'function') addLoraRow(); 
                    const rows = lorasWrapper.querySelectorAll('.row');
                    const lastRow = rows[rows.length - 1]; 
                    if (lastRow) {
                        const sel = lastRow.querySelector('.lora-select') || lastRow.querySelector('.lora-selector');
                        const weight = lastRow.querySelector('.lora-weight') || lastRow.querySelector('.lora-strength-high');
                        const weightL = lastRow.querySelector('.lora-strength-low');
                        if (sel && lora.name) sel.value = lora.name;
                        if (weight && lora.weight) {
                            weight.value = lora.weight;
                            if (weightL) weightL.value = lora.weight; // Sincroniza la L con la H si existe
                        }
                    }
                });
            }
        }
        
        const engineUI = document.getElementById('engineUI');
        const engineToggle = document.getElementById('engineToggle');
        if(engineUI && engineToggle) { engineToggle.checked = true; engineUI.classList.remove('d-none'); }
        
        const loraUI = document.getElementById('loraUI');
        const loraToggle = document.getElementById('loraToggle');
        if(loraUI && loraToggle && config.loras.length > 0) { loraToggle.checked = true; loraUI.classList.remove('d-none'); }

        if (typeof savePreferences === 'function') savePreferences();
        
        // Notificación sutil de carga
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: GartyLang.swal_preset_applied });

    }, 500);
}

// 4. Borrar preset
async function deletePersonalPreset() {
    const selector = document.getElementById('personalPresetSelector');
    const presetName = selector.value;
    if (!presetName) return;

    const result = await SwalDark.fire({
        title: GartyLang.swal_are_u_sure,
        html: `${GartyLang.swal_del_preset_html1} '${presetName}' ${GartyLang.swal_del_preset_html2}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#495057',
        confirmButtonText: `<i class="bi bi-trash"></i> ${GartyLang.btn_siborrar}`,
        cancelButtonText: GartyLang.btn_cancelar
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('/modulos/api_presets.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', name: presetName })
            });
            const res = await response.json();
            if (res.status === 'ok') {
                loadPersonalPresetsList();
                SwalDark.fire({
                    title: GartyLang.swal_deleted,
                    text: GartyLang.swal_preset_vanished,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        } catch (error) {
            console.error(GartyLang.log_err_del_preset, error);
        }
    }
}

// 5. Cargar la lista automáticamente al arrancar
document.addEventListener('DOMContentLoaded', loadPersonalPresetsList);