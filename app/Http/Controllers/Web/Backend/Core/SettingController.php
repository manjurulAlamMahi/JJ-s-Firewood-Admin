<?php

namespace App\Http\Controllers\Web\Backend\Core;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Exception;

class SettingController extends Controller
{
    public function mail()
    {
        return view('backend.layouts.settings.mail');
    }

    public function mailstore(Request $request)
    {
        $request->validate([
            'mail_mailer'           => 'required|string',
            'mail_host'             => 'required|string',
            'mail_port'             => 'required|string',
            'mail_username'         => 'required|string',
            'mail_password'         => 'required|string',
            'mail_encryption'       => 'required|string',
            'mail_from_address'     => 'required|string',
        ]);

        try {
            $envContent = File::get(base_path('.env'));
            $linebreak = "\n";

            $envContent = preg_replace(
                [
                    '/' . 'MAIL_DRIVER' . '=(.*)\s/',
                    '/' . 'MAIL_HOST' . '=(.*)\s/',
                    '/' . 'MAIL_PORT' . '=(.*)\s/',
                    '/' . 'MAIL_USERNAME' . '=(.*)\s/',
                    '/' . 'MAIL_PASSWORD' . '=(.*)\s/',
                    '/' . 'MAIL_ENCRYPTION' . '=(.*)\s/',
                    '/' . 'MAIL_FROM_ADDRESS' . '=(.*)\s/',
                ],
                [
                    'MAIL_DRIVER=' . $request->mail_mailer . $linebreak,
                    'MAIL_HOST=' . $request->mail_host . $linebreak,
                    'MAIL_PORT=' . $request->mail_port . $linebreak,
                    'MAIL_USERNAME=' . $request->mail_username . $linebreak,
                    'MAIL_PASSWORD=' . $request->mail_password . $linebreak,
                    'MAIL_ENCRYPTION=' . $request->mail_encryption . $linebreak,
                    'MAIL_FROM_ADDRESS=' . $request->mail_from_address . $linebreak,
                ],
                $envContent
            );

            if ($envContent !== null) {
                File::put(base_path('.env'), $envContent);
            }

            return redirect()->back()->with('success', 'Mail Settings Updated Successfully');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to Update Mail Settings. Try again.');
        }
    }
}
