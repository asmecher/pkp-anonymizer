# pkp-anonymizer
Anonymize the data in an OJS, OMP, or OPS database.

This script anonymizes:
- User data: usernames, emails, names
- Author data: emails, names
- Submission data: titles, abstracts

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
