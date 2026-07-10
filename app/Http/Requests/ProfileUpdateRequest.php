<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Email is deliberately absent: it is not editable through the profile form (it is the
     * login identifier on a table shared by User and ApiUser across two guards). The
     * controller previously accepted an `email` rule and then unset it before saving — a
     * rule for a field that was always discarded. Only `name` is editable here.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
