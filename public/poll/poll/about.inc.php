<p>
Rather than writing HTML code for every poll item,
You can write for one and loop through all the poll items,
using the following <span class='doc-kw'>foreach loop</span>.

<pre class='doc-code'>
&lt;?php foreach( $poll->getAllItems() as $item ) { ?&gt;

... Write HTML for a poll item here ...

&lt;?php } ?&gt;
</pre>

</p>
