@extends('layouts.admin.main')

@section('content')

<div class="container-fluid">

    @include('admin.modules.alert')

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Terminal</h1>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="font-weight-bold text-primary m-0">Terminal</h6>
                </div>
                <div class="card-body">
                    <form class="form-visibility" action="{{ url('/').'/admin/terminal' }}" method="POST" autocomplete="off">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="terminal_username" class="form-label">Terminal Username</label>
                                    <input class="form-control @error('terminal_username') is-invalid @enderror" type="text" name="terminal_username" id="terminal_username" value="{{ old('terminal_username', $general->terminal_username ?? '') }}" autocomplete="off" />
                                    <div class="form-text">Username displayed in the terminal prompt.</div>
                                    @error('terminal_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
