<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        return view('admin.templates.index');
    }

    public function destroy(string $id)
    {
        Template::findOrFail($id)->delete();
        return redirect('/admin/templates')->with('success', 'Template deleted.');
    }
}
