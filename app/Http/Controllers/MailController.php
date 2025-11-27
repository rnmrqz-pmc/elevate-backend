<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Helpers\CustomHelper;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\WelcomeMail;
use App\Mail\RegisterMail;
use App\Mail\ResetMail;
use App\Mail\TrainingMail;

class MailController extends Controller
{
    private $isDev = false;
    public function __construct(Request $request){
        $apiKey = $request->header('x-api-key');   
        if($apiKey == env('DEV_KEY')){ $this->isDev = true; }
    }

    public function sendMultipleEmails(Request $request)
    {
        $payload = $request->all();
        $receiver = env('APP_ENV') == 'production' ? $payload['receiver'] : env('DEV_EMAIL');
        $data = (object)[
            'username' => $payload['username'] ?? 'testuser',
            'name' => $payload['name'] ?? 'Test User',
            'app_url' => $payload['app_url'] ?? 'https://pmcie.com',
        ];

        Mail::to($receiver)->queue(new RegisterMail($data));
        Mail::to($receiver)->queue(new ResetMail($data));
        Mail::to($receiver)->queue(new TrainingMail($data));
         Mail::to($receiver)->queue(new RegisterMail($data));
        Mail::to($receiver)->queue(new ResetMail($data));
        Mail::to($receiver)->queue(new TrainingMail($data));
         Mail::to($receiver)->queue(new RegisterMail($data));
        Mail::to($receiver)->queue(new ResetMail($data));
        Mail::to($receiver)->queue(new TrainingMail($data));
         Mail::to($receiver)->queue(new RegisterMail($data));
        Mail::to($receiver)->queue(new ResetMail($data));
        Mail::to($receiver)->queue(new TrainingMail($data));
         Mail::to($receiver)->queue(new RegisterMail($data));
        Mail::to($receiver)->queue(new ResetMail($data));
        Mail::to($receiver)->queue(new TrainingMail($data));
         Mail::to($receiver)->queue(new RegisterMail($data));
        Mail::to($receiver)->queue(new ResetMail($data));
        Mail::to($receiver)->queue(new TrainingMail($data));

        return response()->json(['status' => true, 'message' => "Email sent to {$receiver} successfully"]);
    }

    public function testSend(Request $request){

        $payload = $request->all();
        $content = view('emails.reset', [
            'name' => $payload['name'] ?? 'Test User',
            'training_name' => $payload['training_name'] ?? 'Test Training',
            'training_date' => $payload['training_date'] ?? '2024-01-01',
            'training_time' => $payload['training_time'] ?? '10:00 AM',
            'training_location' => $payload['training_location'] ?? 'Test Location',
            'trainer' => $payload['trainer'] ?? 'Test Trainer',
            'resetLink' => $payload['resetLink'] ?? 'https://example.com/reset-link',
        ])->render();
        $receiver = env('APP_ENV') == 'production' ? $payload['receiver'] : env('DEV_EMAIL');
        $subject = $payload['subject'] ?? 'Test Email';

        try {
            Mail::send([], [], function ($message) use ($receiver, $subject, $content) {
                $message->to($receiver)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'))
                        ->html($content); 
            });
            return response()->json(['status' => true, 'message' => "Email sent to {$receiver} successfully"]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function sendMail(Request $request){
        $payload = $request->all();

        $receiver = env('APP_ENV') == 'production' ? $payload['receiver'] : env('DEV_EMAIL');
        $subject = $payload['subject'];
        $body = $payload['body'];

        try {
            Mail::send([], [], function ($message) use ($receiver, $subject, $body) {
                $message->to($receiver)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'))
                        ->html($body); 
            });
            return response()->json(['status' => true, 'message' => "Email sent to {$receiver} successfully"]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function sendResetPasswordEmail(Request $payload)
    {
        $request = $payload->all();

        $user = User::where('email', $request['email'])->first();
        $app_config = DB::table('app_config')->first(); 

        if (!$user) {
            return response()->json([
                'success' => false, 
                'error' => 'User not found',
                'message' => "Incorrect username"
                // 'message' => "User with email " . $request['email'] . " not found"
            ]);
        }

        $trainerID = $user->ID;
        $email = $user->email;
        $name = $user->first_name;
        $expirationDate = date('Y-m-d H:i:s', strtotime($request['datetime'] . ' + ' . $app_config->reset_link_expiry . ' minutes'));
        $appUrl = $request['appUrl'];

        $base64 = base64_encode(json_encode(['trainerID' => $trainerID, 'email' => $email, 'expirationDate' => $expirationDate]));

        DB::table('password_reset_token')->insert([
            'token' => hash('sha256', $base64)
        ]);     

        $resetLink = $appUrl . '/password-reset/' . $base64;
            
        $content = view('emails.reset', [
            'email_logo' => $app_config->email_logo,
            'name' => $name,
            'resetLink' => $resetLink,
            'reset_link_expiry' => $app_config->reset_link_expiry,
            'contact_email' => $app_config->contact_email
        ])->render();

        try {
            $to = $email;
            Mail::send([], [], function ($message) use ($to, $content) {
                $message->to($to)
                        ->subject('Password Reset Request')
                        ->from(config('mail.from.address'), config('mail.from.name'))
                        ->html($content); 
            });
            Log::info('Email sent', ['type' => 'Password Reset', 'receiver' => $email]);
            return response()->json(['status' => true, 'message' => "Email sent to {$email}"]);
        } catch (\Exception $e) {
            Log::error('Error sending email', ['type' => 'Password Reset', 'receiver' => $email, 'error' => $e->getMessage()]);
            return response()->json(['status' => false, 'error' => $e->getMessage()]);
        }


        return;


    }

}
