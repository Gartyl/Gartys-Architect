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

    if (!presetName) return;

    // Recopilar LoRAs
    const loras = [];
    document.querySelectorAll('#lorasWrapper .row').forEach(row => {
        const sel = row.querySelector('.lora-select') || row.querySelector('.lora-selector');
        const weight = row.querySelector('.lora-weight') || row.querySelector('.lora-strength-high');
        if (sel && weight && sel.value !== "") {
            loras.push({ name: sel.value, weight: weight.value });
        }
    });

    const posContentEl = document.getElementById('posContent');
    const negContentEl = document.getElementById('negContent');
    const posPrompt = posContentEl ? posContentEl.innerText.trim() : '';
    const negPrompt = negContentEl ? negContentEl.innerText.trim() : '';

    let width = 1024, height = 1024;
    const aspectEl = document.getElementById('aspectRatio');
    if (aspectEl && aspectEl.value) {
        const parts = aspectEl.value.split('x');
        if(parts.length === 2) {
            width = parseInt(parts[0]);
            height = parseInt(parts[1]);
        }
    }

    // --- AÑADIMOS LA CATEGORÍA A LA FOTOGRAFÍA ---
    const config = {
        categoria: document.getElementById('selector') ? document.getElementById('selector').value : '',
        prompt: posPrompt,
        prompt_negativo: negPrompt,
        modelo: document.getElementById('modelSelector') ? document.getElementById('modelSelector').value : '',
        width: width,
        height: height,
        formato: document.getElementById('formatSelector') ? document.getElementById('formatSelector').value : 'PNG',
        steps: document.getElementById('stepsInput') ? document.getElementById('stepsInput').value : 30,
        cfg: document.getElementById('cfgInput') ? document.getElementById('cfgInput').value : 5.0,
        sampler: document.getElementById('samplerInput') ? document.getElementById('samplerInput').value : 'euler',
        scheduler: document.getElementById('schedulerInput') ? document.getElementById('schedulerInput').value : 'beta',
        seed: document.getElementById('seedInput') ? document.getElementById('seedInput').value : -1,
        flow_shift: document.getElementById('flowShiftInput') ? document.getElementById('flowShiftInput').value : 'Auto',
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
            SwalDark.fire({
                title: GartyLang.swal_saved,
                text: `${GartyLang.swal_preset_ready1} '${presetName}' ${GartyLang.swal_preset_ready2}`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            throw new Error(result.message || 'Error guardando en BD');
        }
    } catch (error) {
        console.error(error);
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

    // --- NUEVO SISTEMA DE CAMBIO DE CATEGORÍA ---
    const currentCategory = document.getElementById('selector') ? document.getElementById('selector').value : '';
    const targetCategory = config.categoria || currentCategory;

    // Si el preset pertenece a una categoría distinta a la que estamos viendo:
    if (targetCategory && targetCategory !== currentCategory) {
        const mainSelector = document.getElementById('selector');
        if (mainSelector) {
            mainSelector.value = targetCategory;
            mainSelector.dispatchEvent(new Event('change')); // Disparamos el "borrón" oficial de la UI
        }
    }

    // Esperamos 300ms a que el borrón haya limpiado y renderizado los HTML correctos, y luego rellenamos:
    setTimeout(() => {
        const posContentEl = document.getElementById('posContent');
        const negContentEl = document.getElementById('negContent');
        
        if (posContentEl && config.prompt) {
            posContentEl.innerText = config.prompt;
            document.getElementById('promptArea').classList.remove('d-none');
            document.getElementById('results').classList.remove('d-none');
            
            const arqActArea = document.getElementById('arquitectoActionArea');
            if (arqActArea) arqActArea.classList.remove('d-none');
        }
        
        if (negContentEl && config.prompt_negativo) {
            negContentEl.innerText = config.prompt_negativo;
            document.getElementById('negativeArea').classList.remove('d-none');
            
            const negToggle = document.getElementById('manualNegativeToggle');
            if(negToggle) {
                negToggle.checked = true;
                document.getElementById('manualNegativeToggleContainer').classList.remove('d-none');
            }
        }

        const modSel = document.getElementById('modelSelector');
        if (modSel && config.modelo) {
             let modelExists = Array.from(modSel.options).some(opt => opt.value === config.modelo);
             if(modelExists) modSel.value = config.modelo;
        }

        if (config.width && config.height) {
            const aspectStr = `${config.width}x${config.height}`;
            const aspectEl = document.getElementById('aspectRatio');
            if (aspectEl) {
                let aspectExists = Array.from(aspectEl.options).some(opt => opt.value === aspectStr);
                if (aspectExists) aspectEl.value = aspectStr; 
            }
        }

        if (config.steps) document.getElementById('stepsInput').value = config.steps;
        if (config.cfg) document.getElementById('cfgInput').value = config.cfg;
        if (config.sampler) document.getElementById('samplerInput').value = config.sampler;
        if (config.scheduler) document.getElementById('schedulerInput').value = config.scheduler;
        if (config.seed !== undefined) document.getElementById('seedInput').value = config.seed;
        if (config.flow_shift) document.getElementById('flowShiftInput').value = config.flow_shift;
        if (config.formato) {
             const formatSel = document.getElementById('formatSelector');
             if(formatSel) formatSel.value = config.formato;
        }

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
                            if (weightL) weightL.value = lora.weight; 
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
        if(loraUI && loraToggle && config.loras && config.loras.length > 0) { 
             loraToggle.checked = true; loraUI.classList.remove('d-none'); 
        }

        if (typeof savePreferences === 'function') savePreferences();
        
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: GartyLang.swal_preset_applied });

    }, 300);
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