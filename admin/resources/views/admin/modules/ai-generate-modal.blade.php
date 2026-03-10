<div class="modal fade" id="aiGenerateModal" tabindex="-1" aria-labelledby="aiGenerateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aiGenerateModalLabel">{{ __('content.generate_with_ai') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ai_modal_type" value="" />
                <input type="hidden" id="ai_modal_field" value="" />
                <input type="hidden" id="ai_modal_input_name" value="" />
                <input type="hidden" id="ai_modal_summernote" value="" />
                <div class="mb-3">
                    <label for="ai_modal_model" class="form-label">{{ __('content.ai_model') }}</label>
                    <select id="ai_modal_model" class="form-select">
                        <option value="">— {{ __('content.ai_model') }} —</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="ai_modal_prompt" class="form-label">{{ __('content.ai_prompt_context') }}</label>
                    <textarea id="ai_modal_prompt" class="form-control" rows="4" placeholder="{{ __('content.ai_prompt_context') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('content.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="ai_modal_generate_btn" data-label="{{ __('content.ai_generate') }}">{{ __('content.ai_generate') }}</button>
            </div>
        </div>
    </div>
</div>
