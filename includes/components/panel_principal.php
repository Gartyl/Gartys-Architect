<div class="row mb-3 align-items-end">
    <div class="col-xl-3 col-lg-3 col-md-6 mb-2 mb-lg-0">
        <label class="small text-secondary fw-bold"><?= __('tit_cat_dest') ?></label>
        <select class="form-select pref-track" id="selector">
            <option value="[LLM]"><?= __('sel_llm') ?></option>
            <option value="[SD15]"><?= __('sel_sd15') ?></option>
            <option value="[SDXL]"><?= __('sel_sdxl') ?></option>
            <option value="[NATURAL_IMAGE]" <?= !$is_pro ? 'disabled' : '' ?>><?= __('sel_flux') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?></option>
            <option value="[VIDEO]" <?= !$is_pro ? 'disabled' : '' ?>><?= __('sel_video') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?></option>
            <option value="[VISION]"><?= __('sel_anal') ?></option>
            <option value="[CHAT]"><?= __('sel_chat') ?></option>
        </select>
    </div>
    
    <div class="col-xl-3 col-lg-3 col-md-6 mb-2 mb-lg-0" id="modelBlock" style="display: none;">
        <label class="small text-secondary fw-bold"><?= __('tit_mod_grafic') ?></label>
        <select class="form-select highlight-model pref-track" id="modelSelector">
            <option value=""><?= __('opt_loading_models') ?></option>
        </select>
    </div>

    <div class="col-xl-3 col-lg-3 col-md-6 mb-2 mb-lg-0" id="llmModelBlock" style="display: none;">
        <label class="small text-info fw-bold"><?= __('tit_mod_ollama') ?></label>
        <select class="form-select border-info highlight-llm pref-track" id="llmModelSelector">
            <option value="" disabled selected><?= __('opt_choose_model') ?></option>
        </select>
    </div>
    
	<div class="col-md-3 mt-3 mt-md-0" id="proporcionIndependienteBlock" style="display: none;">
        <label class="small text-secondary fw-bold"><?= __('tit_proporcion') ?></label>
        <select class="form-select bg-dark text-light border-secondary pref-track" id="aspectRatio" onchange="if(typeof sincRes==='function') sincRes()">
            <option value="1024x1024"><?= __('sel_prop_1') ?></option>
            <option value="896x1152"><?= __('sel_prop_2') ?></option>
            <option value="1344x768"><?= __('sel_prop_3') ?></option>
        </select>
    </div>
    
    <div class="col-md-3 mt-3 mt-md-0" id="batchBlock" style="display: none;">
        <label class="small text-secondary fw-bold"><?= __('tit_cant_img') ?></label>
        <select class="form-select bg-dark text-light border-secondary pref-track" id="batchSize">
            <option value="1"><?= __('sel_cimag_1') ?></option>
            <option value="2"><?= __('sel_cimag_2') ?></option>
            <option value="4"><?= __('sel_cimag_4') ?></option>
        </select>
    </div>
</div>