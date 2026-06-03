<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use \App\Transaccion;

class TransaccionValidada extends Mailable
{
    use Queueable, SerializesModels;

     
    /**
     * The order instance.
     *
     * @var \App\Models\Transaccion
     */
    public $transaccion;
 
    /**
     * Create a new message instance.
     *
     * @param  \App\Models\Transaccion  $order
     * @return void
     */
    public function __construct(Transaccion $transaccion)
    {
        $this->transaccion = $transaccion;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        //return $this->subject('Su descuento en recargos del lote baldío ha sido aplicado.')
        return $this->subject('Su transacción del pago se ha realizado con éxito.')
                    ->from('notificaciones@tesoreriamovil.com.mx', 'Centro')
                    ->view('mail.transaccionvalidada');
    }
}
