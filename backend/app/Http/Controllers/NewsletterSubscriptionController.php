<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:newsletter_subscribers,email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            // Check if email exists but is unsubscribed
            $existingSubscriber = NewsletterSubscriber::withTrashed()
                ->where('email', $request->email)
                ->first();

            if ($existingSubscriber && $existingSubscriber->trashed()) {
                $existingSubscriber->restore();
                $existingSubscriber->update([
                    'status' => 'pending',
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'unsubscribed_at' => null,
                    'ip_address' => $request->ip(),
                    'referrer' => $request->headers->get('referer'),
                ]);
                $existingSubscriber->generateConfirmationToken();

                return response()->json([
                    'message' => 'Please check your email to confirm your subscription',
                    'status' => 'pending',
                ], 202);
            }

            return response()->json([
                'message' => 'Email already subscribed',
            ], 409);
        }

        $subscriber = NewsletterSubscriber::create([
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'status' => 'pending',
            'confirmation_token' => \Illuminate\Support\Str::random(64),
            'unsubscribe_token' => \Illuminate\Support\Str::random(64),
            'ip_address' => $request->ip(),
            'referrer' => $request->headers->get('referer'),
        ]);

        // TODO: Send confirmation email
        // Mail::to($subscriber->email)->send(new NewsletterConfirmation($subscriber));

        return response()->json([
            'message' => 'Please check your email to confirm your subscription',
            'status' => 'pending',
        ], 201);
    }

    public function confirm($token)
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$subscriber) {
            return response()->json([
                'message' => 'Invalid or expired confirmation token',
            ], 404);
        }

        $subscriber->confirm();

        // TODO: Send welcome email
        // Mail::to($subscriber->email)->send(new NewsletterWelcome($subscriber));

        return response()->json([
            'message' => 'Subscription confirmed successfully',
            'subscriber' => $subscriber,
        ]);
    }

    public function unsubscribe($token)
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$subscriber) {
            return response()->json([
                'message' => 'Invalid or expired unsubscribe token',
            ], 404);
        }

        $subscriber->unsubscribe();

        // TODO: Send goodbye email
        // Mail::to($subscriber->email)->send(new NewsletterGoodbye($subscriber));

        return response()->json([
            'message' => 'You have been successfully unsubscribed',
        ]);
    }

    public function status(Request $request)
    {
        $email = $request->query('email');

        if (!$email) {
            return response()->json([
                'message' => 'Email parameter is required',
            ], 400);
        }

        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        if (!$subscriber) {
            return response()->json([
                'subscribed' => false,
                'status' => null,
            ]);
        }

        return response()->json([
            'subscribed' => true,
            'status' => $subscriber->status,
            'subscriber' => $subscriber->only(['id', 'email', 'first_name', 'last_name', 'status']),
        ]);
    }

    public function trackOpen(Request $request, $id)
    {
        $sent = \App\Models\NewsletterSent::where('unsubscribe_token', $id)->first();

        if ($sent) {
            $sent->markAsOpened();
        }

        // Return 1x1 transparent pixel
        return response()->make(base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function trackClick(Request $request, $id)
    {
        $sent = \App\Models\NewsletterSent::where('unsubscribe_token', $id)->first();

        if ($sent) {
            $sent->markAsClicked();
        }

        $url = $request->query('url');

        if ($url) {
            return redirect()->away($url);
        }

        return response()->json(['message' => 'Click tracked']);
    }
}
