# pkp-anonymizer
Anonymize the data in an OJS, OMP, or OPS database.

This script anonymizes:
- User data: usernames, emails, names, passwords
- Author data: emails, names
- Submission data: titles, abstracts
- Integrations:
  - CrossRef username and password
  - ORCiD API credentials

Caveats:
- The generated emails, usernames, and names won't be consistent with each other
- It's sloooooooooooooow on large databases
- It needs added code to remove API keys, passwords, and the like

To use:

1. Copy `config.php-example` to `config.php`
2. Customize `config.php`
3. Run the anonymizer:
   ```sh
   php anonymizer.php
   ```

After executing, it should be possible to log in to any account using the username as the password. Of course, usernames will be anonymized, so it will be necessary to get them from the database.
