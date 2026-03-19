@extends('layouts.admin.main')

@section('content')

<div class="container-fluid">

    @include('admin.modules.alert')

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('content.sections') }}</h1>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="font-weight-bold text-primary m-0">{{ __('content.sections') }}</h6>
                </div>
                <div class="card-body">
                    <form class="form-visibility" action="{{ url('/').'/admin/sections' }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="modal-body">
                            <div class="row">

                                <div class="col-12 mb-2">
                                    <h4 class="mt-3 mb-2 text-gray-800 fw-bold">{{ __('content.abouts_section') }}</h4>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="about_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="about_enable" {{ ($section->about_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="about_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-2">
                                    <h5 class="mt-4 mb-2 text-gray-800 fw-bold">{{ __('content.experience_section') }}</h5>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group mb-2">
                                        <label for="experience_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" name="experience_enable" {{ ($section->experience_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="experience_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="about_experience_order" class="form-label">{{ __('content.order') }}</label>
                                        <select class="form-select" name="about_experience_order" id="about_experience_order" style="max-width: 6rem;">
                                            @foreach ([1, 2, 3, 4] as $n)
                                                <option value="{{ $n }}" {{ (($section->about_experience_order ?? 1) == $n) ? 'selected' : '' }}>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 mb-2">
                                    <h5 class="mt-4 mb-2 text-gray-800 fw-bold">{{ __('content.services_section') }}</h5>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group mb-2">
                                        <label for="services_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" name="services_enable" {{ ($section->services_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="services_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="about_services_order" class="form-label">{{ __('content.order') }}</label>
                                        <select class="form-select" name="about_services_order" id="about_services_order" style="max-width: 6rem;">
                                            @foreach ([1, 2, 3, 4] as $n)
                                                <option value="{{ $n }}" {{ (($section->about_services_order ?? 1) == $n) ? 'selected' : '' }}>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 mb-2">
                                    <h5 class="mt-4 mb-2 text-gray-800 fw-bold">{{ __('content.skills_section') }}</h5>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group mb-2">
                                        <label for="skills_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" name="skills_enable" {{ ($section->skills_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="skills_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="about_skills_order" class="form-label">{{ __('content.order') }}</label>
                                        <select class="form-select" name="about_skills_order" id="about_skills_order" style="max-width: 6rem;">
                                            @foreach ([1, 2, 3, 4] as $n)
                                                <option value="{{ $n }}" {{ (($section->about_skills_order ?? 1) == $n) ? 'selected' : '' }}>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 mb-2">
                                    <h5 class="mt-4 mb-2 text-gray-800 fw-bold">{{ __('content.testimonials_section') }}</h5>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group mb-2">
                                        <label for="testimonial_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" name="testimonial_enable" {{ ($section->testimonial_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="testimonial_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="about_testimonial_order" class="form-label">{{ __('content.order') }}</label>
                                        <select class="form-select" name="about_testimonial_order" id="about_testimonial_order" style="max-width: 6rem;">
                                            @foreach ([1, 2, 3, 4] as $n)
                                                <option value="{{ $n }}" {{ (($section->about_testimonial_order ?? 1) == $n) ? 'selected' : '' }}>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 mb-2">
                                    <hr class="mt-4 mb-5 border-0">
                                    <h4 class="mt-3 mb-2 text-gray-800 fw-bold">{{ __('content.projects_section') }}</h4>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="projects_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="projects_enable" {{ ($section->projects_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="projects_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-2">
                                    <hr class="mt-4 mb-5 border-0">
                                    <h4 class="mt-3 mb-2 text-gray-800 fw-bold">{{ __('content.articles_section') }}</h4>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="articles_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="articles_enable" {{ ($section->articles_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="articles_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-2">
                                    <hr class="mt-4 mb-5 border-0">
                                    <h4 class="mt-3 mb-2 text-gray-800 fw-bold">Terminal</h4>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="terminal_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="terminal_enable" {{ ($section->terminal_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="terminal_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-2">
                                    <hr class="mt-4 mb-5 border-0">
                                    <h4 class="mt-3 mb-2 text-gray-800 fw-bold">Contribute</h4>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="contribute_enable" class="form-label">{{ __('content.enable_section') }}</label>
                                        <div class="form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="contribute_enable" {{ ($section->contribute_enable == 1) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="contribute_enable">{{ __('content.enable') }}</label>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">{{ __('content.update') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
