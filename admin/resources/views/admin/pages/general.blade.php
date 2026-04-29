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
                    <form class="form-visibility info-list-form" action="{{ url('/').'/admin/general' }}" method="POST">
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
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-group info-content">
                                        <label for="social_links" class="form-label">{{ __('content.social_links') }}</label>
                                        <div class="row">
                                            <div class="col-4">
                                                <div class="input-group mb-3">
                                                    <div class="input-group-prepend">
                                                        <button class="btn btn-secondary" type="button" data-bs-toggle="modal" data-bs-target="#iconPickerModal" title="Browse Icons">
                                                            <i class="fas fa-icons" id="icon-picker-preview"></i>
                                                        </button>
                                                    </div>
                                                    <input type="text" class="form-control" name="social_network" id="info_label_social-links" placeholder="fab fa-twitter">
                                                </div>
                                                <small class="form-text text-muted">Use Font Awesome classes (e.g. <code>fab fa-github</code>)</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="input-group mb-3">
                                                    <div class="input-group-prepend"><span class="input-group-text">{{ __('content.link') }}</span></div>
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

<div class="modal fade" id="iconPickerModal" tabindex="-1" role="dialog" aria-labelledby="iconPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="iconPickerModalLabel">Choose an Icon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" id="iconSearchInput" class="form-control" placeholder="Search icons...">
                </div>
                <div id="iconGrid" class="d-flex flex-wrap gap-2" style="max-height: 400px; overflow-y: auto;">
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
