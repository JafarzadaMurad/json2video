@extends('portal.layouts.app')
@section('title', 'Register')

@section('content')
    <div style="display:flex; align-items:center; justify-content:center; min-height: calc(100vh - 65px); padding: 40px 20px;
        background-image: radial-gradient(ellipse at 30% 20%, rgba(100,255,218,0.04) 0%, transparent 50%),
                           radial-gradient(ellipse at 70% 80%, rgba(99,102,241,0.04) 0%, transparent 50%);">
        <div class="card" style="width: 100%; max-width: 440px;">
            <div class="card-header">
                <h3>Create Your Account</h3>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-error">{{ $errors->first() }}</div>
                @endif
                <div class="alert alert-info" style="margin-bottom:16px">🎁 Get <strong>5 minutes free render time</strong>
                    instantly!</div>
                <form method="POST" action="/register">
                    @csrf
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Phone <span class="text-muted">(optional)</span></label>
                        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+994 XX XXX XX XX">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirmation" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Create
                        Account</button>
                </form>
                <p class="text-muted mt-4" style="text-align:center; font-size:13px;">Already have an account? <a
                        href="/login">Sign in</a></p>
            </div>
        </div>
    </div>
@endsection