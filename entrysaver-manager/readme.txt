=== EntrySaver Manager ===
Contributors: faizmuhin
Tags: contact form 7, cf7 submissions, form entries, contact form entries, contact form submissions
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage and view Contact Form 7 submissions directly from the WordPress admin dashboard.

== Description ==

EntrySaver Manager provides a convenient way for site administrators to view and manage Contact Form 7 form submissions directly from the WordPress admin area.

Instead of checking individual submissions through emails or other external systems, administrators can view entries for each Contact Form 7 form from a dedicated entries management interface.

The plugin provides a forms dashboard, individual form entry listings, customizable columns, entry viewing and editing, entry deletion with confirmation, and CSV export.

== Features ==

View Contact Form 7 form entries directly from the WordPress admin.
Dedicated dashboard displaying all Contact Form 7 forms.
View total entry counts for each form.
Display the form shortcode on the forms dashboard.
Display the date and time of the latest submission.
Quickly access entries using the "View Entries" button.
View all entries for an individual Contact Form 7 form.
WordPress-style admin table for displaying form entries.
Customize which form fields are displayed as columns.
Select or deselect fields for the entry listing.
View selected fields directly in the entries table.
View individual entries using an AJAX-powered modal.
Edit individual entries using an AJAX-powered modal.
Delete individual entries with confirmation.
Customize columns from the entries page.
Export form entries to CSV.
Separate entry management for each Contact Form 7 form.

== Forms Dashboard ==

The main EntrySaver Manager page displays available Contact Form 7 forms in an easy-to-use card-based interface.

Each form card provides:

Form title
Total number of entries
Contact Form 7 shortcode
Last submission date and time
View Entries button

Clicking the "View Entries" button opens the dedicated entries page for that form.

== View Form Entries ==

Each Contact Form 7 form has its own entries management page.

Entries are displayed using a WordPress admin-style table. The columns shown in the table can be customized based on the fields available in the selected Contact Form 7 form.

The entries table provides access to actions such as:

View
Edit
Delete

== Customize Columns ==

The "Customize Columns" option allows administrators to control which Contact Form 7 fields are displayed in the entries table.

Clicking the button opens a modal containing the available fields for the selected form.

Administrators can select the fields they want to display as columns. The selected columns are then displayed directly in the entries table.

This makes it possible to show only the information that is relevant for managing submissions.

== View Entry ==

Individual entries can be viewed from the entries table using an AJAX-powered modal.

The modal displays the submitted information for the selected entry without requiring the administrator to leave the entries page.

== Edit Entry ==

Administrators can edit an individual Contact Form 7 entry from the entries table.

The entry editing interface is opened in an AJAX-powered modal, allowing changes to be made without navigating away from the entries listing.

== Delete Entry ==

Entries can be deleted directly from the entries table.

The plugin displays a confirmation before an entry is deleted to help prevent accidental removal of submissions.

== Export Entries ==

Form entries can be exported as a CSV file using the "Export CSV" action available on the entries page.

The export contains the available entry information and selected form fields for the selected Contact Form 7 form.

== Installation ==

Upload the entrysaver-manager folder to the /wp-content/plugins/ directory.
Activate the plugin through the "Plugins" menu in WordPress.
Make sure Contact Form 7 is installed and activated.
Open the EntrySaver Manager page from the WordPress admin menu.
Select a Contact Form 7 form to view and manage its entries.

== Frequently Asked Questions ==

= What is Contact Form 7? =

Contact Form 7 is a WordPress plugin for creating and managing contact forms.

= Where can I view form entries? =

After activating EntrySaver Manager, open the plugin's admin page in the WordPress dashboard. The dashboard displays available Contact Form 7 forms and their entry counts. Click "View Entries" for the form you want to manage.

= Which entries appear in the entries list? =

The entries list includes submissions received through the selected Contact Form 7 form, whether the email notification was successfully sent or failed to send.

The plugin stores the submitted form data independently of the email delivery status, so a submission will still appear in the entries list even if the notification email could not be delivered.

= Can I choose which fields appear in the entries table? =

Yes. Use the "Customize Columns" option on the entries page to select the Contact Form 7 fields that should be displayed as columns.

= Can I edit an entry? =

Yes. Individual entries can be edited from the entries table using the Edit action.

= Can I delete an entry? =

Yes. Entries can be deleted from the entries table. A confirmation is displayed before deletion.

= Can I export entries? =

Yes. The plugin provides an Export CSV action for exporting entries for the selected Contact Form 7 form.

= Does the plugin work with multiple Contact Form 7 forms? =

Yes. The forms dashboard displays the available Contact Form 7 forms, and each form has its own entry management page.

== Requirements ==

WordPress 6.7 or later
PHP 7.4 or later
Contact Form 7

== Screenshots ==

Forms dashboard showing Contact Form 7 forms, entry counts, shortcodes, and latest submissions.
Individual form entries page.
Customize Columns modal.
View Entry modal.
Edit Entry modal.
Export CSV action.

== Changelog ==

= 1.0.0 =

Initial release.
Added Contact Form 7 forms dashboard.
Added individual form entries management.
Added customizable entry columns.
Added AJAX entry view.
Added AJAX entry editing.
Added entry deletion with confirmation.
Added CSV export.