## Jack's Notes
### Preparation
I noticed that some AI-integration bits for Laravel aren't in the repo so I've written an AGENTS.md for the repository
to enforce standards and keep things consistent across multiple agents.

I've also added `laravel/boost` for MCP with PHPStorm and used `laravel/pao` to keep my token usage nice and clean.

Project is using PHP 8.4 but my system version is 8.5. Considered adding a docker-compose.yml to get the exact version
but seems excessive considering there's no DB server or queue.


### Code
My first thing is to move all the logic out of the controllers and into a service layer. This abstraction helps the controller
focus on receiving a request, triggering some logic and then handling the response based on what happens. 

### Deciding
#### Schema
For the saved search schema, I decided to make a name nullable. I might want to name my specific search but then I might not
want to provide a name if I'm just doing a specific search, so we can piece together a "name" on the frontend with the search criteria.

For the actual search criteria, this was a tricky one to decide. I usually try to avoid JSON columns for things like this because
there's no set schema, not to mention how differently MySQL/SQLite/Postgres can handle these fields.
It's flexible but you'd need to consider versioning it and always making sure the frontend works with
very old JSON saved searches. I reckon using columns is the lesser evil here, it means we can query properly, we have a set schema
and can just add new columns. Will make indexing a bit of a pain on anything but `id` and `user_id`.

#### Notification

Seeing the support note about spamming users, I decided to reject creating a duplicate saved search if they accidentally set up
identical alerts. Keeps the database a bit neater and makes sure there's less mess for the user to clear up on their screen so they don't have to delete their duplicates.

Setting a limit of 5 alerts per customer but have made it configurable via config so we don't have to make code changes besides a single number in config. Keeps it aligned across the codebase.

Made sure the common filters `min_bedrooms` are in config so they can be reused.

There could be a scenario where the same property could show up on two different alerts (e.g. choosing 'Any type' and 'Bristol' would get some similar results to 'Detached' and 'Bristol').
Initially I wanted to do what the user requested and send it for both but I can imagine them getting irritated with spam. Since we've had complaints about too many emails, we could use an index here to
make sure that a user is only notified about a property once and whichever saved search picks it up.
If we had a place on the website to view matching properties on a saved search, it'd show up in either of the ones they're viewing anyways. This is solely to avoid spam.

I've decided to use a model observer for the Listing model that'll get fired when a Listing goes live. It stops us from having to
remember to trigger it whenever we set a Listing live and we can hook into it quite nicely in future. Also means we can fire off
a bunch of relevant listeners for the model if we extended this to other features "DoXAfterListingLive"

Going to use a standard Laravel Notification for the actual email, mostly because you can customise where it ends up and the
drivers it uses. It's more of an interface for a notification and can be an email, push notification, DB insert (like in my example code here)
and we don't have to rewrite it or write `SavedSearchNotifyDB`, `SavedSearchNotifyEmail` classes. Love these.
I've just created a `notifications` table for now but in production it'd be running through Horizon or some queue worker.

### Testing the feature
If you want to give it a quick test to see the alert come through:

A saved search has to exist, and a listing has to *become* live while matching it. Creating a listing as live doesn't count
as we're using the observer.

1. Save a search. Easiest is the UI: filter the listings page (e.g max price £500,000) and click the save button. Or if you want to use tinker:

   ```bash
   php artisan tinker --execute 'App\Models\User::orderBy("id")->first()->savedSearches()->create(["max_price" => 500000]);'
   ```

2. Put a matching listing live. There's no admin UI for this yet, so use the cheapest draft so
   the price you're about to set is guaranteed to be under your cap:

   ```bash
   php artisan tinker --execute '$l = App\Models\Listing::where("status", "draft")->orderBy("price")->first(); $l->update(["status" => "live", "listed_at" => now()]);'
   ```

3. Open `/saved-searches/alerts` and should be in there

Flipping a listing live again won't alert twice. Once a pair has been alerted, the `listing_alerts` row blocks it so the user doesn't get spammed by the same alert that might get caught by different searches.

  ```bash
  php artisan tinker --execute 'App\Models\ListingAlert::query()->delete();'
  ```

To reset everything back to a clean slate:

```bash
php artisan tinker --execute 'App\Models\ListingAlert::query()->delete(); DB::table("notifications")->delete(); App\Models\SavedSearch::query()->delete();'
```

### Additional considerations
- I'd make it so customers can edit their saved searches. If I was a customer I'd feel a bit annoyed if I had to start from scratch every time if I wanted to just slightly tweak the price range or area.
- I wouldn't write a backfill for this feature, at least not without input from the projects team to understand how wide we want to cast this net especially with customer emails. Once it's live then it'll go from there, wouldn't want to risk spamming customers with complex commands that check previous alerts.
- On the notifications() relationship I'd get some form of pagination so we're limiting the response at scale.
- The notification itself should have an `implements ShouldQueue` for later once it's plugged up to a queue worker so it can be managed properly, jobs retried if they fail etc... .
- The alerts unread badge is nice but will be on every page so will be requested a bit at scale. I'd probably keep this updated via a websocket channel if we wanted to be really fancy.
- I'd properly abstract out the SavedSearchService so we can make a V2 of it if we need to and switch the implementation out if we did a new version or replacement using an interface. Uses dependency injection that way
- We'd also obviously need a way of changing listings from 'draft' to 'live' for the observer to ever fire
- One major thing to consider for this entire feature is later if we decided to stagger sending multiple matching properties in one email.
