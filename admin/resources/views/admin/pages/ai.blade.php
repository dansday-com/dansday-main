@extends('layouts.admin.main')

@section('content')

<div class="container-fluid">

    @include('admin.modules.alert')

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('content.ai') ?? 'AI' }}</h1>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="font-weight-bold text-primary m-0">{{ __('content.ai') ?? 'AI' }}</h6>
                </div>
                <div class="card-body">
                    <form class="form-visibility" action="{{ url('/').'/admin/ai' }}" method="POST" autocomplete="off">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="ai_url" class="form-label">{{ __('content.ai_gateway_url') ?? 'AI Gateway URL' }}</label>
                                    <input class="form-control @error('ai_url') is-invalid @enderror" type="url" name="ai_url" value="{{ old('ai_url', $general->ai_url ?? '') }}" placeholder="https://..." autocomplete="off" />
                                    <div class="form-text">{{ __('content.ai_gateway_url_desc') ?? 'Base URL for the AI API (OpenAI compatible).' }}</div>
                                    @error('ai_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="ai_key" class="form-label">{{ __('content.ai_api_key') ?? 'AI API Key' }}</label>
                                    <input class="form-control @error('ai_key') is-invalid @enderror" type="password" name="ai_key" value="{{ ($general->ai_key ?? '') !== '' ? preg_replace('/./', '*', $general->ai_key) : '' }}" autocomplete="new-password" />
                                    <div class="form-text">{{ __('content.ai_api_key_desc') ?? 'API key. Leave blank to keep the current key.' }}</div>
                                    @error('ai_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group mt-4">
                                    <label for="ai_model" class="form-label">{{ __('content.ai_model') ?? 'AI Model' }}</label>
                                    <input class="form-control @error('ai_model') is-invalid @enderror" type="text" name="ai_model" value="{{ old('ai_model', $general->ai_model ?? '') }}" placeholder="your-model-alias" autocomplete="off" />
                                    <div class="form-text">{{ __('content.ai_model_desc') ?? 'Model name sent to the AI gateway.' }}</div>
                                    @error('ai_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="ai_terminal_prompt" class="form-label">{{ __('content.ai_terminal_prompt') ?? 'Terminal System Prompt' }}</label>
                                    <textarea class="form-control @error('ai_terminal_prompt') is-invalid @enderror" name="ai_terminal_prompt" id="ai_terminal_prompt" rows="7">{{ old('ai_terminal_prompt', $general->ai_terminal_prompt ?? '') }}</textarea>
                                    <div class="form-text">{{ __('content.ai_terminal_prompt_desc') ?? 'System prompt for the AI terminal. Leave blank to use the default.' }}</div>
                                    @error('ai_terminal_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="ai_content_prompt" class="form-label">{{ __('content.ai_content_prompt') ?? 'Content Generation System Prompt' }}</label>
                                    <textarea class="form-control @error('ai_content_prompt') is-invalid @enderror" name="ai_content_prompt" id="ai_content_prompt" rows="7">{{ old('ai_content_prompt', $general->ai_content_prompt ?? '') }}</textarea>
                                    <div class="form-text">{{ __('content.ai_content_prompt_desc') ?? 'System prompt for content generation. Leave blank to use the default.' }}</div>
                                    @error('ai_content_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">{{ __('content.update') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection