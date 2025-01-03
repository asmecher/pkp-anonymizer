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

	if (!Semver::satisfies($this->version, '^3.3.0.0')) throw new Exception('This database is too old for the anonymizer to process.');
    }

    public function users() : void
    {
	$locales = $this->db->table('user_settings')
	    ->where('locale', '<>', '')
	    ->select('locale')->distinct()->pluck('locale', 'locale')->toArray();
	$localizedFakers = array_map(fn($locale) => Faker\Factory::create($locale), $locales);

	// Ensure no existing emails and usernames are accidentally used
	$usernames = $this->db->table('users')->pluck('username')->toArray();
	$emails = $this->db->table('users')->pluck('email')->toArray();

        foreach ($this->db->table('users AS u')->select('u.*')->get() as $user) {
	    // Determine a unique username and email for the user
	    do {
		$email = $this->faker->email();
		$username = strtok($email, '@');
	    } while (in_array($email, $emails) || in_array($username, $usernames));
	    DB::table('users')->where('user_id', $user->user_id)->update([
		'email' => $email,
		'username' => $username,
	    ]);
	    $usernames[] = $username;
	    $emails[] = $email;

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
	    }
	}
    }

    public function authors() : void
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
    }

    public function publications() : void
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
		    ->where('setting_name', 'title')
		    ->update(['setting_value' => $localizedFakers[$locale]->sentence()]);
		DB::table('publication_settings')
		    ->where('publication_id', $publication->publication_id)
		    ->where('locale', $locale)
		    ->where('setting_name', 'abstract')
		    ->update(['setting_value' => $localizedFakers[$locale]->paragraph()]);
	    }
	}
    }

    public function crossref() : void
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
    }

    public function orcid() : void
    {
	if (Semver::satisfies($this->version, '^3.5.0.0')) throw new Exception('The anonymizer does not yet support ORCID settings for 3.5.0.');

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

    }
}
