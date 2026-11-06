<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class AdminSettingController extends Controller
{
    public function edit()
    {
        $settings = SystemSetting::getSettings();
        return view('backend.layouts.admin_setting.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'admin_title'           => 'nullable|string|max:150',
                'short_title'           => 'nullable|string|max:100',
                'admin_logo'            => 'nullable|image|max:2048',
                'admin_favicon'         => 'nullable|image|max:1024',
                'admin_copyright_text'  => 'nullable|string|max:1000',
            ]);

            $settings = SystemSetting::getSettings();
            $data = collect($validated)->except(['admin_logo', 'admin_favicon'])->toArray();

            foreach (['admin_logo' => 'logo', 'admin_favicon' => 'favicon'] as $field => $folder) {
                if ($request->hasFile($field)) {
                    if ($settings->$field && file_exists(public_path($settings->$field))) {
                        unlink(public_path($settings->$field));
                    }
                    $data[$field] = $this->uploadFile($request->file($field), "uploads/admins/{$folder}");
                }
            }

            if ($settings->exists) {
                $settings->update($data);
            } else {
                SystemSetting::create($data);
            }

            return back()->with('success', 'Admin settings updated successfully!');
        } catch (\Exception $e) {
            Log::error('Admin settings update failed: ' . $e->getMessage());
            return back()->with('error', 'Update failed. Please try again.');
        }
    }

    private function uploadFile($file, $path)
    {
        $directory = public_path($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move($directory, $filename);

        return "{$path}/{$filename}";
    }
}
