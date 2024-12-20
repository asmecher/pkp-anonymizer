<?php

use Illuminate\Database\Capsule\Manager as DB;
use Faker\Generator;

class Anonymizer {
    protected DB $db;
    protected Generator $faker;

    protected $localeMap = [
	'en' => 'en_US',
    ];

    public function __construct (DB $db) {
	$this->db = $db;
	$this->faker = Faker\Factory::create();
    }

    public function users() {
	$locales = $this->db->table('user_settings')
	    ->where('locale', '<>', '')
	    ->select('locale')->distinct()->pluck('locale', 'locale')->toArray();
	$localizedFakers = array_map(fn($locale) => Faker\Factory::create($locale), $locales);

	$usernames = $emails = [];
        foreach ($this->db->table('users AS u')->select('u.*')->get() as $user) {
	    // Determine a unique username and email for the user
	    do {
		$email = $this->faker->email();
		$username = strtok($email, '@');
	    } while (in_array($email, $emails) && in_array($username, $usernames));
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

    public function authors() {
	$locales = $this->db->table('author_settings')
	    ->where('locale', '<>', '')
	    ->select('locale')->distinct()->pluck('locale', 'locale')->toArray();
	$localizedFakers = array_map(fn($locale) => Faker\Factory::create($locale), $locales);

	$emails = [];
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

    public function publications() {
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
}
