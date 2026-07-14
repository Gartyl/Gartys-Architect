<!-- ======================================================= -->
<!-- 5.8. COLOREADO NEURAL (DDColor - Exclusivo PRO) -->
<!-- ======================================================= -->
<?php 
    $has_ddcolor_access = ($user_rol === 'pro' || $is_admin); 
?>

<div class="param-group shadow-sm border-danger mb-3" id="ddcolorBlock" style="display: none; border-color: rgba(220, 53, 69, 0.4) !important; background: rgba(220, 53, 69, 0.05);">
    <div class="d-flex justify-content-between align-items-center">
        <label class="small text-danger fw-bold mb-0">
            <i class="bi bi-palette-fill"></i> <?= __('tit_ddcolor') ?? 'COLOREADO NEURAL (DDColor)' ?>
            <?php if (!$has_ddcolor_access): ?>
                <span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem;">PRO</span>
            <?php endif; ?>
        </label>
        <div class="form-check form-switch m-0">
            <input class="form-check-input pref-track border-danger" type="checkbox" id="toggleDDColor" name="ddcolor_enabled" value="1" 
                   style="cursor: pointer;"
                   onchange="document.getElementById('ddcolorSubOptions').classList.toggle('d-none', !this.checked); if(!this.checked) { document.getElementById('pureDDColorToggle').checked = false; toggleDDColorPuro(false); }"
                   <?= !$has_ddcolor_access ? 'disabled title="'.(__('msg_solo_pro') ?? 'Función exclusiva PRO').'"' : '' ?>>
        </div>
    </div>
    
    <div id="ddcolorSubOptions" class="d-none mt-3 pt-3 border-top border-danger" style="border-color: rgba(220, 53, 69, 0.2) !important;">
        <!-- Selector de Modelo -->
        <div class="mb-3">
            <label class="form-label text-danger small fw-bold mb-1"><?= __('lbl_ddcolor_model') ?? 'Modelo de Color' ?></label>
            <select class="form-select form-select-sm bg-dark text-light border-danger" name="ddcolor_model" id="ddcolor_model">
                <option value="ddcolor_artistic.pth"><?= __('opt_ddcolor_art') ?? 'DDColor Artístico (Alta Calidad)' ?></option>
                <option value="ddcolor_paper_tiny.pth"><?= __('opt_ddcolor_tiny') ?? 'DDColor Tiny (Ultra Rápido)' ?></option>
            </select>
        </div>
      
        <!-- Modo Puro -->
<!--        <div class="form-check form-switch m-0">
            <input class="form-check-input border-danger" type="checkbox" id="pureDDColorToggle" onchange="toggleDDColorPuro(this.checked)">
            <label class="form-check-label small text-danger fw-bold" for="pureDDColorToggle" style="cursor: pointer;">
                <i class="bi bi-shield-lock me-1"></i> <?= __('ctrl_ddcolor_puro') ?? 'Modo Puro (DDColor)' ?>
            </label>
        </div>
-->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('toggleDDColor');
    const collapse = document.getElementById('collapseDDColor');
    if (toggle && collapse) {
        toggle.addEventListener('change', function() {
            const bsCollapse = new bootstrap.Collapse(collapse, { toggle: false });
            this.checked ? bsCollapse.show() : bsCollapse.hide();
        });
    }
});
</script>