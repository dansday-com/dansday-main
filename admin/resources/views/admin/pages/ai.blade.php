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
                                <div class="form-group mt-4">
                                    <label for="ai_content_model" class="form-label">{{ __('content.ai_content_model') }}</label>
                                    <input class="form-control @error('ai_content_model') is-invalid @enderror" type="text" name="ai_content_model" value="{{ old('ai_content_model', $general->ai_content_model ?? '') }}" placeholder="your-model-alias" autocomplete="off" />
                                    <div class="form-text">{{ __('content.ai_content_model_desc') }}</div>
                                    @error('ai_content_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group mt-4">
                                    <label for="ai_terminal_reasoning" class="form-label">{{ __('content.ai_terminal_reasoning') }}</label>
                                    <select class="form-control @error('ai_terminal_reasoning') is-invalid @enderror" name="ai_terminal_reasoning" id="ai_terminal_reasoning">
                                        @foreach(['none' => 'None – Disable reasoning', 'minimal' => 'Minimal – Lowest reasoning effort', 'low' => 'Low – Low reasoning effort', 'medium' => 'Medium – Default reasoning effort', 'high' => 'High – High reasoning effort', 'xhigh' => 'XHigh – Maximum reasoning effort'] as $value => $label)
                                            <option value="{{ $value }}" {{ old('ai_terminal_reasoning', $general->ai_terminal_reasoning ?? 'none') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('ai_terminal_reasoning')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group mt-4">
                                    <label for="ai_content_reasoning" class="form-label">{{ __('content.ai_content_reasoning') }}</label>
                                    <select class="form-control @error('ai_content_reasoning') is-invalid @enderror" name="ai_content_reasoning" id="ai_content_reasoning">
                                        @foreach(['none' => 'None – Disable reasoning', 'minimal' => 'Minimal – Lowest reasoning effort', 'low' => 'Low – Low reasoning effort', 'medium' => 'Medium – Default reasoning effort', 'high' => 'High – High reasoning effort', 'xhigh' => 'XHigh – Maximum reasoning effort'] as $value => $label)
                                            <option value="{{ $value }}" {{ old('ai_content_reasoning', $general->ai_content_reasoning ?? 'none') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('ai_content_reasoning')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="ai_terminal_prompt" class="form-label">{{ __('content.ai_terminal_prompt') }}</label>
                                    <textarea class="form-control @error('ai_terminal_prompt') is-invalid @enderror" name="ai_terminal_prompt" id="ai_terminal_prompt" rows="7">{{ old('ai_terminal_prompt', $general->ai_terminal_prompt ?? '') }}</textarea>
                                    <div class="form-text">{{ __('content.ai_terminal_prompt_desc') }}</div>
                                    @error('ai_terminal_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group mb-4">
                                    <label for="ai_article_prompt" class="form-label">{{ __('content.ai_article_prompt') }}</label>
                                    <textarea class="form-control @error('ai_article_prompt') is-invalid @enderror" name="ai_article_prompt" id="ai_article_prompt" rows="7">{{ old('ai_article_prompt', $general->ai_article_prompt ?? '') }}</textarea>
                                    <div class="form-text">{{ __('content.ai_article_prompt_desc') }}</div>
                                    @error('ai_article_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="ai_project_prompt" class="form-label">{{ __('content.ai_project_prompt') }}</label>
                                    <textarea class="form-control @error('ai_project_prompt') is-invalid @enderror" name="ai_project_prompt" id="ai_project_prompt" rows="7">{{ old('ai_project_prompt', $general->ai_project_prompt ?? '') }}</textarea>
                                    <div class="form-text">{{ __('content.ai_project_prompt_desc') }}</div>
                                    @error('ai_project_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <hr class="my-4">
                        <h6 class="font-weight-bold text-primary mb-3">Embedding (Optional)</h6>
                        <p class="text-muted small mb-3">Configure an embedding API for semantic search. Leave all fields empty to use keyword search only.</p>
                        <p class="text-muted small mb-3">New content (including synced GitHub activity) is picked up by the embedding worker (<code class="small">php artisan embeddings:work</code>), which processes <strong>one missing row at a time</strong> with pauses—not once per minute on a timer. It runs in Docker via supervisord next to the web server. Use “Generate All Embeddings” for a full refresh or when text changed.</p>
                        <div class="mb-3">
                            <button type="button" id="embed-all-btn" class="btn btn-outline-secondary btn-sm" title="Generate All Embeddings">
                                <i class="fas fa-database me-1"></i> Generate All Embeddings
                            </button>
                            <span id="embed-all-status" class="ms-2 small text-muted"></span>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="embedding_url" class="form-label">Embedding API URL</label>
                                    <input class="form-control @error('embedding_url') is-invalid @enderror" type="url" name="embedding_url" value="{{ old('embedding_url', $general->embedding_url ?? '') }}" placeholder="https://..." autocomplete="off" />
                                    <div class="form-text">Base URL for the embedding API endpoint.</div>
                                    @error('embedding_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="embedding_key" class="form-label">Embedding API Key</label>
                                    <input class="form-control @error('embedding_key') is-invalid @enderror" type="password" name="embedding_key" value="{{ ($general->embedding_key ?? '') !== '' ? preg_replace('/./', '*', $general->embedding_key) : '' }}" autocomplete="new-password" />
                                    <div class="form-text">API key for the embedding service. Leave blank to keep current.</div>
                                    @error('embedding_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="embedding_model" class="form-label">Embedding Model</label>
                                    <input class="form-control @error('embedding_model') is-invalid @enderror" type="text" name="embedding_model" value="{{ old('embedding_model', $general->embedding_model ?? '') }}" placeholder="text-embedding-3-small" autocomplete="off" />
                                    <div class="form-text">Model name for generating embeddings.</div>
                                    @error('embedding_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
