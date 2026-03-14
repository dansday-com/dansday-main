<ul class="navbar-nav bg-gradient-dark sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center py-3" href="{{ url('/admin/general') }}">
        <span class="sidebar-brand-text text-white">Admin</span>
    </a>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        {{ __('menu.pages') }}
    </div>

    <li class="nav-item {{ request()->is('admin/home*') ? 'active' : '' }}">
        <a class="nav-link css3animate" href="{{ url('admin/home') }}">
            <i class="fas fa-home css3animate"></i>
            <span>{{ __('menu.home') }}</span>
        </a>
    </li>

    <li class="nav-item {{ (request()->is('admin/skills*') ||
                            request()->is('admin/experiences*') ||
                            request()->is('admin/testimonials*') ||
                            request()->is('admin/services*')) ? 'active' : '' }}">
        <a class="nav-link css3animate padding-sm {{ (request()->is('admin/skills*') ||
                                                        request()->is('admin/experiences*') ||
                                                        request()->is('admin/testimonials*') ||
                                                        request()->is('admin/services*')) ? '' : 'collapsed' }}" data-bs-toggle="collapse"
            href="#collapseAbouts" role="button" aria-expanded="false" aria-controls="collapseAbouts">
            <i class="fas fa-user css3animate"></i>
            <span>{{ __('menu.abouts') }}</span>
        </a>
        <div id="collapseAbouts" class="collapse {{ (request()->is('admin/skills*') ||
                                                    request()->is('admin/experiences*') ||
                                                    request()->is('admin/testimonials*') ||
                                                    request()->is('admin/services*')) ? 'show' : '' }}">
            <div class="bg-white py-2 collapse-inner rounded">
                <h6 class="collapse-header">{{ __('menu.abouts_sections') }}:</h6>
                <a class="collapse-item {{ request()->is('admin/skills*') ? 'active' : '' }}" href="{{ url('admin/skills') }}">{{ __('menu.skills') }}</a>
                <a class="collapse-item {{ request()->is('admin/experiences*') ? 'active' : '' }}" href="{{ url('admin/experiences') }}">{{ __('menu.experiences') }}</a>
                <a class="collapse-item {{ request()->is('admin/testimonials*') ? 'active' : '' }}" href="{{ url('admin/testimonials') }}">{{ __('menu.testimonials') }}</a>
                <a class="collapse-item {{ request()->is('admin/services*') ? 'active' : '' }}" href="{{ url('admin/services') }}">{{ __('menu.services') }}</a>
            </div>
        </div>
    </li>

    <li class="nav-item {{ (request()->is('admin/projects/projects*') ||
                            request()->is('admin/projects/project*') ||
                            request()->is('admin/projects/categories*')) ? 'active' : '' }}">
        <a class="nav-link css3animate padding-sm {{ (request()->is('admin/projects/projects*') ||
                                                        request()->is('admin/projects/project*') ||
                                                        request()->is('admin/projects/categories*')) ? '' : 'collapsed' }}" data-bs-toggle="collapse"
            href="#collapseProjects" role="button" aria-expanded="false" aria-controls="collapseProjects">
            <i class="fas fa-briefcase css3animate"></i>
            <span>{{ __('menu.projects') }}</span>
        </a>
        <div id="collapseProjects" class="collapse {{ (request()->is('admin/projects/projects*') ||
                                                    request()->is('admin/projects/project*') ||
                                                    request()->is('admin/projects/categories*')) ? 'show' : '' }}">
            <div class="bg-white py-2 collapse-inner rounded">
                <h6 class="collapse-header">{{ __('menu.projects_section') }}:</h6>
                <a class="collapse-item {{ (request()->is('admin/projects/projects*') ||
                                            request()->is('admin/projects/project*') ) ? 'active' : '' }}" href="{{ url('admin/projects/projects') }}">{{ __('menu.projects') }}</a>
                <a class="collapse-item {{ request()->is('admin/projects/categories*') ? 'active' : '' }}" href="{{ url('admin/projects/categories') }}">{{ __('menu.categories') }}</a>
            </div>
        </div>
    </li>

    <li class="nav-item {{ (request()->is('admin/articles/posts*') ||
                            request()->is('admin/articles/post*') ||
                            request()->is('admin/articles/categories*')) ? 'active' : '' }}">
        <a class="nav-link css3animate padding-sm {{ (request()->is('admin/articles/posts*') ||
                                                        request()->is('admin/articles/post*') ||
                                                        request()->is('admin/articles/categories*')) ? '' : 'collapsed' }}" data-bs-toggle="collapse"
            href="#collapseArticles" role="button" aria-expanded="false" aria-controls="collapseArticles">
            <i class="fas fa-newspaper css3animate"></i>
            <span>{{ __('menu.articles') }}</span>
        </a>
        <div id="collapseArticles" class="collapse {{ (request()->is('admin/articles/posts*') ||
                                                    request()->is('admin/articles/post*') ||
                                                    request()->is('admin/articles/categories*')) ? 'show' : '' }}">
            <div class="bg-white py-2 collapse-inner rounded">
                <h6 class="collapse-header">{{ __('menu.articles_sections') }}:</h6>
                <a class="collapse-item {{ (request()->is('admin/articles/posts*') ||
                                            request()->is('admin/articles/post*') ) ? 'active' : '' }}" href="{{ url('admin/articles/posts') }}">{{ __('menu.articles_list') }}</a>
                <a class="collapse-item {{ request()->is('admin/articles/categories*') ? 'active' : '' }}" href="{{ url('admin/articles/categories') }}">{{ __('menu.categories') }}</a>
            </div>
        </div>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        {{ __('menu.settings_options') }}
    </div>

    <!-- GENERAL ITEM -->
    <li class="nav-item {{ request()->is('admin/general*') ? 'active' : '' }}">
        <a class="nav-link css3animate padding-sm" href="{{ url('admin/general') }}">
            <i class="fas fa-cog css3animate"></i>
            <span>{{ __('menu.general') }}</span>
        </a>
    </li>

    <li class="nav-item {{ request()->is('admin/ai*') ? 'active' : '' }}">
        <a class="nav-link css3animate padding-sm" href="{{ url('admin/ai') }}">
            <i class="fas fa-robot css3animate"></i>
            <span>{{ __('menu.ai') ?? 'AI' }}</span>
        </a>
    </li>

    <li class="nav-item {{ request()->is('admin/sections*') ? 'active' : '' }}">
        <a class="nav-link css3animate padding-sm" href="{{ url('admin/sections') }}">
            <i class="fas fa-puzzle-piece css3animate"></i>
            <span>{{ __('menu.sections') }}</span>
        </a>
    </li>

    <hr class="sidebar-divider">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0 css3animate" id="sidebarToggle"></button>
    </div>

</ul>