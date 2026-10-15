@extends('layouts.app')
@section('title', 'Editar usuario')
@section('heading', 'Editar usuario')
@section('content')
<form class="rounded-2xl bg-white p-6 shadow-sm" method="POST" action="{{ route('users.update', $user) }}">
    @include('users._form')
</form>
@endsection
