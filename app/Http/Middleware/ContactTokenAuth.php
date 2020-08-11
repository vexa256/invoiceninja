<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use App\Events\Contact\ContactLoggedIn;
use App\Models\ClientContact;
use App\Models\CompanyToken;
use App\Models\User;
use App\Utils\Ninja;
use Closure;

class ContactTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->header('X-API-TOKEN') && ($client_contact = ClientContact::with(['company'])->whereRaw("BINARY `token`= ?", [$request->header('X-API-TOKEN')])->first())) {
            $error = [
                'message' => 'Authentication disabled for user.',
                'errors' => new \stdClass
            ];

            //client_contact who once existed, but has been soft deleted
            if (!$client_contact) {
                return response()->json($error, 403);
            }


            $error = [
                'message' => 'Access is locked.',
                'errors' => new \stdClass
            ];

            //client_contact who has been disabled
            if ($client_contact->is_locked) {
                return response()->json($error, 403);
            }

            //stateless, don't remember the contact.
            auth()->guard('contact')->login($client_contact, false);
            
            event(new ContactLoggedIn($client_contact, $client_contact->company, Ninja::eventVars())); //todo
        } else {
            $error = [
                'message' => 'Invalid token',
                'errors' => new \stdClass
            ];

            return response()->json($error, 403);
        }

        return $next($request);
    }
}
