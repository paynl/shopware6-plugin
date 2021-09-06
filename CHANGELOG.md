# 1.4.5
- Added the Refunding status

# 1.4.4
- The default calendar has returned
- Fixed the bug on change payment methods for the German version of the site

# 1.4.3
- Improved choosing payment methods
- Improved saving payment methods additional information

# 1.4.2
- Fixed the bug on choosing native payment methods
- Fixed "paynl-kvk-coc-number-field" JS bug

# 1.4.1
- Improved user permissions
- Fixed partially paid bug
- Fixed payment denied notice
- Added payment screen language settings

# 1.4.0
- Plugin is now Shopware 6.4 compatible

# 1.3.5
- Fixed the bug with shipping cost for not logged-in customers
- Fixed the bug with changing payment status for non PAY. payment methods
- Fixed the bug with changing default payment method after saving configs or reinstalling payment methods
- Fixed the bug with auto activating Phone number and Birthday fields after saving plugin settings
- Code improvement after code quality analysis

# 1.3.4
- Fixed an issue which occurred when a user requested a new password for his account

# 1.3.3
- fixed deletion of plugin credentials after plugin Uninstall
- fixed emails sending after order status change
- fixed CustomerRegisterSubscriber so that it works properly with CLI
- KVK/CoC input field was moved from 'Address' to 'Personal' block
- templates improvements

# 1.3.2
- DoB and Phonenumber fields are now required for filling in(for PayLater methods)
- updated date-picker

# 1.3.1
- added the functionality of making the CoC code required or not
- added the functionality for disabling/enabling the PAY. styles
- code improvements and code refactoring

# 1.3.0
- fixed the bug with tax rate calculation
- fixed the bug with the refund processing
- added the snippet for "Order confirmation email has been sent" for the Successful payments
- added validation for the Phonenumber field
- improved mobile responsiveness

# 0.3.3
- added the feature of a compulsory selection of iDEAL bank issuer
- added the feature of a unified payment method
- improved templates inheritance
- fixed the bug with the CoC number

# 0.3.2
- Fixed the bug with empty brand data
- Fixed the bug with Shipping method edit on Complete order page

# 0.3.1
- Corrected the order canceling message  for the Dutch version
- Fixed responsive design for the fields DoB and Phone number
- Changed PM title from 'Name' to 'Visible Name'
- Added limitation for PMs depiction in the footer (now max. 5)
- Added admin functionality of choosing whether to show PMs description
- Code improvements (refactoring)
- Minor CSS fixes

# 0.3.0
- Added messages for Order Finished page: for Pending - "Payment is being verified by administrator"; For Paid - "Payment successful!"
- Added descriptions from API for certain PMs
- Added functionality for choosing a bank issuer for iDeal
- Added "KVK/COC Number" field and placeholder "Enter your COC number" for  a default billing address (for Belgium and the Netherlands)
- Added functionality of canceling an order. After that, a link to "Change Payment Method" appears
- Added translations for "Order" label in transaction data for the Dutch language, edited translations for English and German languages
- Added new icons for PMs
- Added functionality of deleting PM icons from the Shopware storage when Uninstalling plugin
- Added the option of changing the Order Transaction Status from "In Progress" to "Authorize" or "Verify"
- Added functionality if PAY. Transaction status is "Pending" - set Order Transaction Status "In Progress"
- Added DoB to transaction data
- Corrected labels for Save/Change Payment Method buttons
- Fixed the bug on Edit Order page in Admin Panel
- Bug fixes, minor code improvements

# 0.2.3
- Added Shopware and Plugin version to transaction data
- Added text to order description for clarification

# 0.2.2
- Added uploading payment methods icons to media files
- For the checkout form, we have added Save/Close buttons on the right side of payment methods at the payment methods modal
- Put Pay. transactions module as the entry point of Orders
- Added validation fields highlights for required plugin settings fields; also checking if credentials are valid

# 0.2.1
- Added vendor prefixes for all custom components
- Added separate page for plugin configuration
- Removed some unnecessary files
- Minor code style fixes

# 0.1.0
- Implemented support for all payment methods by Pay.nl for Shopware v6.1
- Tested on these Shopware versions: 6.1.0, 6.1.1, 6.1.2, 6.1.3, 6.1.4, 6.1.5
