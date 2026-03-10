@extends('layouts.admin.main')

@section('content')

<div class="container-fluid">

    @include('admin.modules.alert')

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('content.general') }}</h1>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="font-weight-bold text-primary m-0">{{ __('content.general') }}</h6>
                </div>
                <div class="card-body">
                    <form class="form-visibility info-list-form" action="{{ url('/').'/admin/general' }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label for="title" class="form-label">{{ __('content.title') }}</label>
                                        <input class="form-control @error('title') is-invalid @enderror" type="text" name="title" value="{{ $general->title }}" />
                                        @error('title')<div class="invalid-feedback">{{ __('content.text_not_valid') }} {{ __('content.max_characters') }}: 55.</div>@enderror
                                    </div>
                                    <div class="form-group mb-4">
                                        <label for="description" class="form-label">{{ __('content.description') }}</label>
                                        <input class="form-control @error('description') is-invalid @enderror" type="text" name="description" value="{{ $general->description }}" />
                                        @error('description')<div class="invalid-feedback">{{ __('content.text_not_valid') }} {{ __('content.max_characters') }}: 255.</div>@enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="analytics_code" class="form-label">{{ __('content.analytics_code') }}</label>
                                        <input class="form-control @error('analytics_code') is-invalid @enderror" type="text" name="analytics_code" value="{{ $general->analytics_code }}" />
                                        <div class="form-text d-flex"><span>{{ __('content.analytics_code_desc') }}</span></div>
                                        @error('analytics_code')<div class="invalid-feedback">{{ __('content.text_not_valid') }} {{ __('content.max_characters') }}: 255.</div>@enderror
                                    </div>
                                    <div class="form-group mb-4">
                                        <label for="openai_url" class="form-label">Open AI URL</label>
                                        <input class="form-control @error('openai_url') is-invalid @enderror" type="url" name="openai_url" value="{{ old('openai_url', $general->openai_url ?? '') }}" placeholder="https://..." />
                                        <div class="form-text">Base URL for the internal OpenAI-compatible API.</div>
                                        @error('openai_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="openai_key" class="form-label">Open AI Key</label>
                                        <input class="form-control @error('openai_key') is-invalid @enderror" type="password" name="openai_key" value="" placeholder="{{ ($general->openai_key ?? '') !== '' ? '••••••••' : '' }}" autocomplete="off" />
                                        <div class="form-text">API key. Leave blank to keep the current key.</div>
                                        @error('openai_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-group mt-4">
                                        <label for="openai_model" class="form-label">Open AI Model</label>
                                        <input class="form-control @error('openai_model') is-invalid @enderror" type="text" name="openai_model" value="{{ old('openai_model', $general->openai_model ?? '') }}" placeholder="your-model-alias" />
                                        <div class="form-text">Model name sent to the internal gateway.</div>
                                        @error('openai_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-group mb-5">
                                        <label for="image_favicon" class="form-label d-flex justify-content-between">
                                            {{ __('content.image_favicon') }}
                                            <span class="fw-normal fst-italic remove-image text-primary" data-target="image_favicon" data-url="{{ asset('/') }}"><i class="fas fa-times mr-1"></i>{{ __('content.remove_image') }}</span>
                                        </label>
                                        <div class="d-flex p-3 mb-3 bg-gray-200 justify-content-center">
                                            <img src="{{ upload_url($general->image_favicon) }}" class="img-fluid img-maxsize-200 previewImage_image_favicon" />
                                        </div>
                                        <input class="form-control previewImage @error('image_favicon') is-invalid @enderror" type="file" name="image_favicon" value="" accept="image/jpeg,image/png" />
                                        <input type="hidden" name="image_favicon_current" value="{{ $general->image_favicon }}" />
                                        <div class="form-text">
                                            <span>{{ __('content.image_requirements_png') }}</span>
                                        </div>
                                        @error('image_favicon')<div class="invalid-feedback">{{ __('content.error_validation_image') }}</div>@enderror
                                    </div>
                                    <div class="form-group info-content">
                                        <label for="social_links" class="form-label">{{ __('content.social_links') }}</label>
                                        <div class="row">
                                            <div class="col-3">
                                                <div class="input-group mb-3">
                                                    <select class="form-select select-social" name="social_network" id="info_label_social-links">
                                                        @foreach ($social_icons as $icon)
                                                            <option value="{{ $icon['code'] }}">{{ $icon['name'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-7">
                                                <div class="input-group mb-3">
                                                    <div class="input-group-append"><span class="input-group-text">{{ __('content.link') }}</span></div>
                                                    <input type="text" name="social_links" id="info_value_social-links" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <button type="button" class="btn btn-success w-100 addInfo" data-target="social-links">{{ __('content.add') }}</button>
                                            </div>
                                            <div class="invalid-feedback d-none invalid-social-links">{{ __('content.characters_not_valid') }}</div>
                                        </div>
                                        <input type="hidden" name="social_links" value="{{ $general->social_links }}" id="social-links" />
                                        <div class="table-elements p-4">
                                            <table class="table table-info-list table-social-links w-100" data-target="social-links">
                                                <tbody>
                                                    @php
                                                    $social_links = json_decode($general->social_links, true);
                                                    if (!empty($social_links)):
                                                        foreach ($social_links as $key => $value) {
                                                            echo '<tr><td class="fw-bold"><span class="'.$value["title"].'"></span></td><td>'.$value["text"].'</td><td class="text-right"><button type="button" class="btn btn-outline-danger btn-sm rounded-circle deleteInfo" data-info="'.$value["title"].'" data-value="'.$value["text"].'"><i class="fas fa-times"></i></button></tr>';
                                                        }
                                                    endif;
                                                    @endphp
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary check-summernote">{{ __('content.update') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
