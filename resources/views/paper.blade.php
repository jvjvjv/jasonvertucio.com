@extends('layout')
@section('title','I Did A Thing!')
@section('main')
<script type="text/javascript" src="https://s3.amazonaws.com/widgets.paper.li/javascripts/sr.iframe.min.js"></script>
<script type="text/javascript">
  Paperli.PaperFrame.Show({
    domain: 'paper.li',
    pid: '54eca9f4-7593-4461-9c1a-47078a17be7c'
  });
</script>
@endsection