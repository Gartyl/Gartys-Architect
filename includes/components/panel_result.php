<div id="progressContainer" class="d-none mt-4 border-top border-secondary pt-4">
	<div class="d-flex justify-content-between align-items-center small text-muted fw-bold mb-1">
		<span id="progressText" class="text-info"><?= __('prog_rendering') ?? 'Renderizando...' ?></span>
		<div>
			<span id="progressPercent" class="text-info me-3">0%</span>
			<span class="badge bg-danger shadow-sm border border-danger" style="cursor:pointer; opacity: 0.9;" onclick="forzarCancelacionTarea()" title="<?= __('prog_btn_kill_title') ?? 'Matar tarea atascada y liberar interfaz' ?>">
				<i class="bi bi-x-circle fw-bold"></i> <?= __('btn_cancelar') ?? 'Cancelar' ?>
			</span>
		</div>
	</div>
	<div class="hacker-progress"><div class="hacker-progress-bar" id="progressBar"></div></div>
</div>

<div id="imageResult" class="mt-4 text-center"></div>