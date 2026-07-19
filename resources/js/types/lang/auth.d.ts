interface AuthLang {
   // Authentication Messages
   failed: string;
   password: string;
   throttle: string;
   password_updated: string;
   verification_link_sent: string;
   password_reset_sent: string;
   google_auth_settings: string;
   google_auth_description: string;

   // Login Page
   login_title: string;
   login_description: string;
   login_audiences_heading: string;
   login_audience_admin: string;
   login_audience_admin_hint: string;
   login_audience_trainer: string;
   login_audience_trainer_hint: string;
   login_audience_internal: string;
   login_audience_internal_hint: string;
   login_audience_external: string;
   login_audience_external_hint: string;
   login_external_register_note: string;
   remember_me: string;
   forgot_password: string;
   continue_with: string;
   no_account: string;
   google_auth: string;

   // Register Page
   register_title: string;
   register_description: string;
   have_account: string;
   register_learner_type_note: string;
   register_required_fields_note: string;

   // Forgot Password
   forgot_description: string;
   return_to_login: string;

   // Reset Password
   reset_title: string;
   reset_description: string;

   // Confirm Password
   confirm_title: string;
   confirm_description: string;

   // Verify Email
   verify_title: string;
   verify_description: string;
   verification_sent: string;
   change_email: string;
}
