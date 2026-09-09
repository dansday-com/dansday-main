<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>

    <ul class="navbar-nav ml-auto">

        <li class="nav-item dropdown nav-item-user no-arrow">
            <a class="nav-link dropdown-toggle text-gray-600" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" 
            aria-expanded="false">
                <span class="mr-2 d-none d-lg-inline small">{{ $user->name }}</span>
                @php
                if ($user->image != null) {
                    echo '<img src="'.upload_url($user->image).'" alt="'.$user->name.'" class="img-fluid rounded-circle bg-gray-200 p-1" />';
                } else {
                    echo '<i class="fas fa-user-circle h4 m-0"></i>';
                }
                @endphp
            </a>
            
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in px-5 py-4" aria-labelledby="userDropdown">
                
                <div class="d-flex align-items-center user-info">
                    <div class="flex-shrink-0">
                        @php
                        if ($user->image != null) {
                            echo '<img src="'.upload_url($user->image).'" alt="'.$user->name.'" class="img-fluid rounded-circle bg-gray-200 p-1" />';
                        } else {
                            echo '<i class="fas fa-user-circle h4 m-0"></i>';
                        }
                        @endphp
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <a href="{{ url('admin/profile') }}" title="{{ $user->name }}">{{ $user->name }}</a>
                        <p class="m-0">{{ $user->email }}</p>
                    </div>
                </div>
                
                <hr class="dropdown-divider my-3">
                
                <a class="dropdown-item text-gray-600" href="{{ url('admin/profile') }}">
                    <i class="fas fa-list fa-sm fa-fw mr-2"></i>
                    {{ __('menu.user_profile') }}
                </a>
                <a class="dropdown-item text-gray-600" href="{{ route('logout') }}"
                onclick="event.preventDefault();document.getElementById('logout.form').submit()">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2"></i>
                    {{ __('menu.logout') }}
                </a>
                <form id="logout.form" action="{{ route('logout') }}" method="post" style="display: none">
                    @csrf
                </form>
            </div>

        </li>

    </ul>

</nav>
