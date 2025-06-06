# 1.7.2
- Added iDEAL and PayPal express buttons added to product page for a faster checkout experience
- Various new payment methods added
- Small under the hood updates

# 1.7.1
- Improved validation to enhance security and transaction integrityInc

# 1.7.0
- Added compatibility with Shopware 6.7
- Improved fast checkout transaction validation

# 1.6.9
- Added support for native refund API
- Fixed PaynlDatePicker error

# 1.6.8
- Fixed storefront scripts for Shopware 6.6 related to express checkout

# 1.6.7
- Fixed issue relating to finish URL error

# 1.6.6
- Implemented automated customer logout after redirecting to the shop for express payments
- Adjusted the checkout page layout to display express payment buttons side-by-side for better visual alignment and user convenience
- Fixed the issue with the customer redirecting to the finished URL
- Fixed the calculation of shipping costs on the refund page
- Added a modal window for iDEAL fast checkout

# 1.6.5
- Fixed error related to missing requested payment method
- Fixed error related to invalid payment token
- Existing payment methods name, logo and description are no longer overwritten

# 1.6.4
- Fast checkout button for PayPal added
- Fast checkout button for iDEAL added

# 1.6.3
- Fixed install payment methods issue

# 1.6.2
- Added refund, cancel, and back buttons on the refund page
- Moved the shipping and product section to the top of the refund page
- Added restock all option on the refund page
- Fixed error on checkout confirm page
- Fixed refund option on Shopware 6.6 and 6.5

# 1.6.1
- Added restoring shopping cart after clicking back on the merchant payment page
- Added refund tab in the order view
- Fixed cancel exchange error

# 1.6.0
- Support Shopware 6.6 version
- Added logging functionality

# 1.5.16
- Fixed the wrong order amount when the line item price was set manually

# 1.5.15
- Fixed shipping costs calculation
- Fixed order state update

# 1.5.14
- Fixed service class name
- Fixed date picker for IOS devices

# 1.5.13
- Added new payment methods
- Payment method ID now taken from order

# 1.5.12
- Fixed notify route for POST requests
- Failover exchange improved
- Fixed issue with paymentOptionId not being sent
- Made iDEAL bank selection optional with a toggle

# 1.5.11
- Fixed error in surcharging after cancellation
- Improved support for multistore

# 1.5.10
- Multicore functionality added

# 1.5.9
- Improved working of failover gateway

# 1.5.8
- Added failover gateway support

# 1.5.7
- Fixed finish-details template
- Added IP settings

# 1.5.6
- Fixed Pay. request notification issue

# 1.5.5
- Improved checkout flow
- Fix for changing date of birth

# 1.5.4
- Updated storefront styles
- Added automatic capture and void functionality

# 1.5.3
- Fixed Payment Surcharge product image on Shopware 6.4

# 1.5.2
- Added Payment Surcharging

# 1.5.1
- Added Google analytics
- Added plugin config button

# 1.5.0
- Support Shopware 6.5 version

# 1.4.19
- Fixed updating payment status from partly paid to the paid state

# 1.4.18
- Code improvements and bug fixes

# 1.4.17
- Added Refunding status Dutch translation

# 1.4.16
- Added payment method Blik
- Added payment method Biller
- Added payment method Shoes & Sneakers Giftcard
- Added payment method Your Green Giftcard
- Added payment method Bataviastad Giftcard
- Added payment method Online banking
- Added payment method Monizze
- Added payment method Sodexo
- Fixed storefront bugs

# 1.4.15
- Added the CoC number field on the customer group registration page
- Displayed the CoC number field for all countries
- Updated the AfterPay logo

# 1.4.14
- Rewritten the Order actions template
- Changed the plugin name and logo

# 1.4.13
- Fixed the Payment status bug

# 1.4.12
- Optimized the process of sending customer names to PAY.
- Fixed the JS errors in the plugin configuration settings
- Fixed configuration settings bug on Shopware 6.4.10. The Paynl plugin was disabled from configuration settings
- Refactored the storefront code

# 1.4.11
- Fixed Javascript error "HTML element not found" in the current Shopware version 6.4.8.

# 1.4.10
- Added order state automation based on payment state

# 1.4.9
- Added the ability to pay by terminal
- Fixed updating payment statuses
- Fixed payment notify message

# 1.4.8
- Overwriting payments statuses was fixed

# 1.4.7
- Added the multichannel support
- Removed the save button for IDEAL payment method

# 1.4.6
- Fixed editing profile template

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
