@extends('layouts.app') @section('heading','Registrar empleado') @section('content')
<form class='rounded-2xl bg-white p-6 shadow-sm' method='POST' action='{{ route('employees.store') }}'>@include('employees._form')</form>
@endsection
