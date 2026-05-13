@extends('errors.layout')

@section('status', '404')

@php
    // use Symfony\Component\HttpKernel\Exception\HttpException;
    // $message = ($exception instanceof HttpException && $exception->getMessage())
    //     ? $exception->getMessage()
    //     : "The page you're looking for doesn't exist.";
    $message = "The page you're looking for doesn't exist.";
@endphp

@section('message', $message)
