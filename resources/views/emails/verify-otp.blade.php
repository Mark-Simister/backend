<x-mail::message>
# Email Verification Required  

Hi **{{ $name }}**,  

Thank you for registering with **{{ config('app.name') }}**.  
To complete your registration, please use the verification code below:

---

# {{ $otp }}

---

This code will expire in **{{ $ttlMinutes }} minutes**.  

<x-mail::button :url="''">
Verify My Account
</x-mail::button>

If you did not request this registration, please ignore this email.  

Thanks & Regards,  
**The {{ config('app.name') }} Team**  
</x-mail::message>
