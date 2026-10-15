@extends('layouts.app')
@section('title', 'Registrar usuario')
@section('heading', 'Registrar usuario')
@section('content')
<form class="rounded-2xl bg-white p-6 shadow-sm" method="POST" action="{{ route('users.store') }}">
    @include('users._form')
</form>
@endsection
