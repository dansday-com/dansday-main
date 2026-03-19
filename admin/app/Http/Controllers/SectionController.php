<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SectionController extends Controller
{
    public function index()
    {
        $section = Section::find(1);
        if (! $section) {
            if (! auth()->check()) {
                return redirect()->route('login')->with('message', 'Initial data not found. Please run: php artisan db:seed');
            }
            Log::error('Initial data not found (Section). Please run: php artisan db:seed', [
                'user_id' => auth()->id(),
                'path' => request()->path(),
            ]);
            abort(500, 'Initial data not found. Please run: php artisan db:seed');
        }
        $user = User::find(1);
        return view('admin.pages.sections')
            ->with('section', $section)
            ->with('user', $user);
    }

    public function update(Request $request)
    {
        Section::where('id', 1)->update([
            'about_enable' => $request->has('about_enable') ? 1 : 0,
            'about_experience_order' => (int) $request->input('about_experience_order', 0),
            'about_services_order' => (int) $request->input('about_services_order', 0),
            'about_skills_order' => (int) $request->input('about_skills_order', 0),
            'about_testimonial_order' => (int) $request->input('about_testimonial_order', 0),
            'experience_enable' => $request->has('experience_enable') ? 1 : 0,
            'skills_enable' => $request->has('skills_enable') ? 1 : 0,
            'testimonial_enable' => $request->has('testimonial_enable') ? 1 : 0,
            'services_enable' => $request->has('services_enable') ? 1 : 0,
            'projects_enable'   => $request->has('projects_enable') ? 1 : 0,
            'articles_enable'   => $request->has('articles_enable') ? 1 : 0,
            'terminal_enable'   => $request->has('terminal_enable') ? 1 : 0,
            'contribute_enable' => $request->has('contribute_enable') ? 1 : 0,
        ]);
        return redirect('/admin/sections')->with('ok-update', '');
    }
}
