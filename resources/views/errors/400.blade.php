@extends('errors::minimal')

@section('title', 'Bad Request')
@section('code', '400')
@section('message', $exception->getMessage() ?: 'Bad Request')
