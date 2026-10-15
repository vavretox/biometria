@extends('layouts.app') @section('heading','Editar empleado') @section('content')
<form class='rounded-2xl bg-white p-6 shadow-sm' method='POST' action='{{ route('employees.update',$employee) }}'>@include('employees._form')</form>
@endsection
