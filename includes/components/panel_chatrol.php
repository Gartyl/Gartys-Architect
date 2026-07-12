<?php
try {
	$stmtRoles = $pdo->prepare("SELECT titulo, prompt_texto FROM personalidades_prompts WHERE tipo = 'chat_personality' AND activo = 1 AND UPPER(idioma) = UPPER(?) ORDER BY id ASC");
	$stmtRoles->execute([$lang]);
	$rolesDB = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	$rolesDB = []; 
}
?>
<div class="col-md-12 mb-3" id="chatRoleBlock" style="display: none;">
	<div class="param-group shadow-sm border-success" style="background: rgba(35, 134, 54, 0.05); border-color: rgba(35, 134, 54, 0.4) !important;">
		<label class="small text-success fw-bold mb-2">
			<i class="bi bi-person-lines-fill me-1"></i> <?= __('tit_personaje'); ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
		</label>
														
		<select class="form-select bg-dark text-light border-success mb-2 pref-track" id="chatRoleSelector" name="chat_role" onchange="document.getElementById('customRoleInput').classList.toggle('d-none', this.value !== 'custom')" <?= !$is_pro ? 'disabled' : '' ?>>
			
			<option value="">🤖 <?= __('rol_asistente_defecto') ?></option>
			
			<?php if (!empty($rolesDB)): ?>
				<?php foreach ($rolesDB as $role): ?>
					<option value="<?= htmlspecialchars($role['prompt_texto']); ?>">
						<?= htmlspecialchars($role['titulo']); ?>
					</option>
				<?php endforeach; ?>
			<?php endif; ?>
			
			<option value="custom">🎭 <?= __('sel_chat_per') ?></option>
		</select>
		
		<textarea class="form-control bg-dark text-light border-success d-none pref-track" id="customRoleInput" name="custom_role" rows="2" placeholder="<?= __('ph_rol_custom') ?>"></textarea>
	</div>
</div>