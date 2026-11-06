<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactUs;
use Illuminate\Support\Facades\Validator;

class ContactUsController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,webp|max:40960',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $contactUs = new ContactUs();
            $contactUs->name = $request->input('name');
            $contactUs->email = $request->input('email');
            $contactUs->subject = $request->input('subject');
            $contactUs->message = $request->input('message');

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/contact-us'), $filename);
                $contactUs->attachment = 'uploads/contact-us/' . $filename;
            }

            $contactUs->save();

            // Return full URL for the attachment in the response
            if ($contactUs->attachment) {
                $contactUs->attachment = asset($contactUs->attachment);
            }

            return response()->json([
                'success' => true,
                'message' => 'Contact Us entry submitted successfully.',
                'data' => $contactUs
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
