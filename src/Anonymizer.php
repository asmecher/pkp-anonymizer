<?php

use Illuminate\Database\Capsule\Manager as DB;
use Faker\Generator;
use Composer\Semver\Semver;

class Anonymizer {
    protected DB $db;
    protected Generator $faker;

    protected $localeMap = [
        'en' => 'en_US',
    ];

    /** @var $version string The version of OJS/OMP/OPS being anonymized */
    public string $version;

    /** @var $product string The name of the product being anonymized */
    public string $product;

    /**
     * Get the name of the context settings table for the product being anonymized.
     */
    protected function getContextSettingsTableName() : string
    {
        return match($this->product) {
            'ojs2' => 'journal_settings',
            'omp' => 'press_settings',
            'ops' => 'server_settings',
        };
    }

    public function __construct (DB $db) {
        $this->db = $db;
        $this->faker = Faker\Factory::create();

        $versions = $this->db->table('versions')
            ->where('current', 1)
            ->whereIn('product', ['ojs2', 'omp', 'ops'])
            ->get();
        if (count($versions) != 1) throw new Exception('Could not determine software version!');

        $version = $versions->first();
        $this->version = "{$version->major}.{$version->minor}.{$version->revision}.{$version->build}";
        $this->product = $version->product;

        if (!Semver::satisfies($this->version, '^3.3.0.0')) throw new Exception('This database is too old for the anonymizer to process.');
    }

    public function users() : self
    {
        $locales = $this->db->table('user_settings')
            ->where('locale', '<>', '')
            ->select('locale')->distinct()->pluck('locale', 'locale')->toArray();
        $localizedFakers = array_map(fn($locale) => Faker\Factory::create($locale), $locales);

        $this->db->table('users')->select('*')->orderBy('user_id')->chunk(1000, function($users) use ($locales, $localizedFakers) {
	    foreach ($users as $user) {
                // Determine a unique username and email for the user. If we hit a duplicate,
                // try again.
                while (true) {
                    try {
                        $email = $this->faker->email();
                        $username = strtok($email, '@');

                        DB::table('users')->where('user_id', $user->user_id)->update([
                            'email' => $email,
                            'username' => $username,
                            'password' => sha1($username . $username), // Set the password to the username via grandfathered sha1 encryption
                        ]);
                        break;
                    } catch (\Illuminate\Database\QueryException $e) {
                        // If we had an error other than a duplicate, throw it again.
                        if ($e->errorInfo[1] != 1062) throw $e;
                    }
                }

                foreach ($locales as $locale) {
                    DB::table('user_settings')
                        ->where('user_id', $user->user_id)
                        ->where('locale', $locale)
                        ->where('setting_name', 'familyName')
                        ->update(['setting_value' => $localizedFakers[$locale]->lastName()]);
                    DB::table('user_settings')
                        ->where('user_id', $user->user_id)
                        ->where('locale', $locale)
                        ->where('setting_name', 'givenName')
                        ->update(['setting_value' => $localizedFakers[$locale]->firstName()]);
                    DB::table('user_settings')
                        ->where('user_id', $user->user_id)
                        ->where('locale', $locale)
                        ->where('setting_name', 'biography')
                        ->update(['setting_value' => $localizedFakers[$locale]->paragraph()]);
                    DB::table('user_settings')
                        ->where('user_id', $user->user_id)
                        ->where('locale', $locale)
                        ->where('setting_name', 'preferredPublicName')
                        ->update(['setting_value' => $localizedFakers[$locale]->name()]);
                    DB::table('user_settings')
                        ->where('user_id', $user->user_id)
                        ->where('locale', $locale)
                        ->where('setting_name', 'signature')
                        ->update(['setting_value' => $localizedFakers[$locale]->sentence()]);
                    DB::table('user_settings')
                        ->where('user_id', $user->user_id)
                        ->where('locale', $locale)
                        ->where('setting_name', 'orcid')
                        ->update(['setting_value' => 'http://orcid.org/1234-5678-9012-3456']); // This is just about certainly invalid
	        }
	    }
        });
        return $this;
    }

    public function authors() : self
    {
        $locales = $this->db->table('author_settings')
            ->where('locale', '<>', '')
            ->select('locale')->distinct()->pluck('locale', 'locale')->toArray();
        $localizedFakers = array_map(fn($locale) => Faker\Factory::create($locale), $locales);

        // Ensure no existing emails are accidentally used
        $emails = $this->db->table('users')->pluck('email')->toArray();

        foreach ($this->db->table('authors AS a')->select('a.*')->get() as $author) {
            $emails = [];
            do {
                $email = $this->faker->email();
            } while (in_array($email, $emails));
            DB::table('authors')->where('author_id', $author->author_id)->update([
                'email' => $email,
            ]);
            $emails[] = $email;

            foreach ($locales as $locale) {
                DB::table('author_settings')
                    ->where('author_id', $author->author_id)
                    ->where('locale', $locale)
                    ->where('setting_name', 'familyName')
                    ->update(['setting_value' => $localizedFakers[$locale]->lastName()]);
                DB::table('author_settings')
                    ->where('author_id', $author->author_id)
                    ->where('locale', $locale)
                    ->where('setting_name', 'givenName')
                    ->update(['setting_value' => $localizedFakers[$locale]->firstName()]);
            }
        }
        return $this;
    }

    public function publications() : self
    {
        $locales = $this->db->table('author_settings')
            ->where('locale', '<>', '')
            ->select('locale')->distinct()->pluck('locale', 'locale')->toArray();
        $localizedFakers = array_map(fn($locale) => Faker\Factory::create($locale), $locales);

        foreach ($this->db->table('publications AS p')->select('p.*')->get() as $publication) {
            foreach ($locales as $locale) {
                DB::table('publication_settings')
                    ->where('publication_id', $publication->publication_id)
                    ->where('locale', $locale)
                    ->whereIn('setting_name', ['title', 'cleanTitle'])
                    ->update(['setting_value' => $localizedFakers[$locale]->sentence()]);
                DB::table('publication_settings')
                    ->where('publication_id', $publication->publication_id)
                    ->where('locale', $locale)
                    ->where('setting_name', 'subtitle')
                    ->update(['setting_value' => $localizedFakers[$locale]->words(2)]);
                DB::table('publication_settings')
                    ->where('publication_id', $publication->publication_id)
                    ->where('locale', $locale)
                    ->where('setting_name', 'abstract')
                    ->update(['setting_value' => $localizedFakers[$locale]->paragraph()]);
                DB::table('publication_settings')
                    ->where('publication_id', $publication->publication_id)
                    ->where('locale', $locale)
                    ->where('setting_name', 'citationsRaw')
                    ->update(['setting_value' => $localizedFakers[$locale]->paragraph()]);
            }
        }
        return $this;
    }

    public function reviews() : self
    {
        DB::table('review_form_responses')->update(['response_value' => null]);
        foreach (DB::table('review_form_responses')->where('response_type', 'string')->get() as $reviewFormResponse) {
            DB::table('review_form_responses')->where('review_form_element_id', $reviewFormResponse->review_form_element_id)
                ->where('review_id', $reviewFormResponse->review_id)
                ->where('response_type', 'string')
                ->update(['response_value' => $this->faker->sentence()]);
        }

        foreach (DB::table('submission_comments')->get() as $comment) {
            DB::table('submission_comments')
                ->where('comment_id', $comment->comment_id)
                ->update([
                    'comment_title' => $this->faker->sentence(),
                    'comments' => $this->faker->paragraph(),
                ]);
        }

        return $this;
    }

    public function emailLog() : self
    {
        DB::table('email_log')->select('*')->orderBy('log_id')->chunk(1000, function($logEntries) {
            foreach ($logEntries as $logEntry) {
                DB::table('email_log')
                    ->where('log_id', $logEntry->log_id)
                    ->update([
                        'from_address' => $this->faker->email(),
                        'recipients' => '"' . $this->faker->name() . '" <' . $this->faker->email() . '>',
                        'cc_recipients' => '',
                        'bcc_recipients' => '',
                        'subject' => $this->faker->sentence(),
                        'body' => $this->faker->paragraph(),
                    ]);
            }
        });

        return $this;
    }

    public function crossref() : self
    {
        // 3.3.0 has plugin_name = 'crossrefexportplugin'; 3.4.0 and 3.5.0 use 'crossrefplugin' instead
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['crossrefexportplugin', 'crossrefplugin'])
            ->where('setting_name', 'depositorEmail')
            ->update(['setting_value' => $this->faker->email()]);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['crossrefexportplugin', 'crossrefplugin'])
            ->where('setting_name', 'depositorName')
            ->update(['setting_value' => $this->faker->name()]);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['crossrefexportplugin', 'crossrefplugin'])
            ->where('setting_name', 'password')
            ->update(['setting_value' => $this->faker->password()]);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['crossrefexportplugin', 'crossrefplugin'])
            ->where('setting_name', 'username')
            ->update(['setting_value' => $this->faker->username()]);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['crossrefexportplugin', 'crossrefplugin'])
            ->where('setting_name', 'testmode')
            ->update(['setting_value' => '1']);

        return $this;
    }

    public function datacite() : self
    {
        // 3.3.0 has plugin_name = 'dataciteexportplugin'; 3.4.0 and 3.5.0 use 'dataciteplugin' instead
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['dataciteexportplugin', 'dataciteplugin'])
            ->where('setting_name', 'username')
            ->update(['setting_value' => $this->faker->username()]);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['dataciteexportplugin', 'dataciteplugin'])
            ->where('setting_name', 'password')
            ->update(['setting_value' => $this->faker->password()]);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['dataciteexportplugin', 'dataciteplugin'])
            ->where('setting_name', 'testUsername')
            ->update(['setting_value' => $this->faker->username()]);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['dataciteexportplugin', 'dataciteplugin'])
            ->where('setting_name', 'testPassword')
            ->update(['setting_value' => $this->faker->password()]);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['dataciteexportplugin', 'dataciteplugin'])
            ->where('setting_name', 'testMode')
            ->update(['setting_value' => '1']);
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['dataciteexportplugin', 'dataciteplugin'])
            ->where('setting_name', 'testDOIPrefix')
            ->update(['setting_value' => '10.1234']);

        return $this;
    }

    public function orcid() : self
    {
        // 3.5.0: Context settings
        $this->db->table($this->getContextSettingsTableName())
            ->whereIn('setting_name', ['orcidClientId', 'orcidClientSecret'])
            ->update(['setting_value' => $this->faker->password()]);

        // 3.3.0 and 3.4.0: Plugin settings
        $this->db->table('plugin_settings')->where('plugin_name', 'orcidprofileplugin')
            ->where('setting_name', 'orcidClientId')
            ->update(['setting_value' => $this->faker->password()]);
        $this->db->table('plugin_settings')->where('plugin_name', 'orcidprofileplugin')
            ->where('setting_name', 'orcidClientSecret')
            ->update(['setting_value' => $this->faker->password()]);
        $this->db->table('plugin_settings')->where('plugin_name', 'orcidprofileplugin')
            ->where('setting_name', 'isSandbox')
            ->update(['setting_value' => '1']);

        return $this;
    }

    public function lucene() : self
    {
        $this->db->table('plugin_settings')->where('plugin_name', 'luceneplugin')
            ->where('setting_name', 'username')
            ->update(['setting_value' => $this->faker->username()]);
        $this->db->table('plugin_settings')->where('plugin_name', 'luceneplugin')
            ->where('setting_name', 'password')
            ->update(['setting_value' => $this->faker->password()]);

        return $this;
    }

    public function ithenticate() : self
    {
        // v2 iThenticate API: delete the API key and API URLs
        $this->db->table('plugin_settings')->where('plugin_name', 'plagiarismplugin')
            ->whereIn('setting_name', ['ithenticateApiKey', 'ithenticateApiUrl'])
            ->delete();
        $this->db->table($this->getContextSettingsTableName())
            ->where('setting_name', 'ithenticateWebhookId')
            ->delete();

        // v1 iThenticate API: anonymize the usernames and passwords
        $this->db->table('plugin_settings')->where('plugin_name', 'plagiarismplugin')
            ->where('setting_name', 'ithenticateUser')
            ->update(['setting_value' => $this->faker->username()]);
        $this->db->table('plugin_settings')->where('plugin_name', 'plagiarismplugin')
            ->where('setting_name', 'ithenticatePass')
            ->update(['setting_value' => $this->faker->password()]);

        return $this;
    }

    public function doaj() : self
    {
        $this->db->table('plugin_settings')->where('plugin_name', 'doajexportplugin')
            ->where('setting_name', 'apiKey')
            ->update(['setting_value' => $this->faker->password()]);
        $this->db->table('plugin_settings')->where('plugin_name', 'doajexportplugin')
            ->where('setting_name', 'testMode')
            ->update(['setting_value' => '1']);

        return $this;
    }

    public function portico() : self
    {
        $this->db->table('plugin_settings')->whereIn('plugin_name', ['app\plugins\importexport\portico\porticoexportplugin', 'porticoplugin'])
            ->where('setting_name', 'endpoints')
            ->delete();

        return $this;
    }

    public function paypal() : self
    {
        // Delete all Paypal settings
        $this->db->table('plugin_settings')->where('plugin_name', 'paypalpayment')
            ->delete();

        return $this;
    }
}
