<table>
  <tbody>
    <tr>
      <th>From:</th>
      <td>
        {{ $comment->name }} <{{ $comment->email}}>
      </td>
    </tr>
    <tr>
      <th>Date:</th>
      <td>
        {{ $comment->created_at }}
      </td>
    </tr>
    <tr>
      <th>Comment:</th>
      <td>
        {{ $comment->message }}
      </td>
    </tr>
  </tbody>
</table>