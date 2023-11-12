<?php

namespace App\Http\Controllers;

use App\Mail\NewImageUploadedMail;
use App\Models\Galery;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'file' => 'required|file|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $file = $request->file("file");
        if ($file->isValid()) {
            $extension = $file->getClientOriginalExtension();
            $fileName = md5($file->getContent()) . '.' . $extension;

            // Move the uploaded file to the storage
            $path = $request->file('file')->storeAs('public/uploads', $fileName);

            // Store information in the database
            $image = Galery::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'path' => $fileName,
            ]);

            // Get all users
            $users = DB::table('users')->select('email', 'name')->get();

            // Send email to each user
            foreach ($users as $user) {
                Mail::to($user->email)
                    ->send(new NewImageUploadedMail($image, $user));
            }

            Session::flash('success', 'File uploaded successfully.');

            return redirect()->route('uploads');
        }

        Session::flash('error', 'File upload failed.');
        return redirect()->back();
    }

    public function file()
    {
        $paths = $this->getAllUploads();

        return view('uploads.upload', compact('paths'));
    }

    public function getAllUploads()
    {
        $paths = Galery::all();

        return $paths;
    }

    public function delete($filename)
    {
        $filePath = 'uploads/' . $filename;

        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);

            $file = Galery::where('path', $filename)->first();
            $file->delete();
            return redirect()->back()->with('success', 'Image deleted successfully.');
        }

        return redirect()->back()->with('error', 'Image not found.');
    }


    public function show($id){
        $image = Galery::find($id);
        return view('uploads.show', compact('image'));
    }
}
