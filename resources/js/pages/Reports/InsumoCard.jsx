// resources/js/Components/InsumoCard.jsx
import { Card, Text, Group, Badge, Button, Loader } from '@mantine/core';
import { IconPrinter } from '@tabler/icons-react';
import { useState } from 'react';
import axios from 'axios';

export default function InsumoCard({ item }) {
  const [loading, setLoading] = useState(false);

  const openPdf = async () => {
    try {
      setLoading(true);
      const response = await axios.get(route('insumos.pdf', item.id), {
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(response.data);
      window.open(url, '_blank');
    } catch (e) {
      console.error('Error descargando PDF', e);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card
      withBorder
      padding='xl'
      radius='md'
      style={{ width: '100%', maxWidth: 350 }}
    >
      <Group
        justify='space-between'
        mb='sm'
      >
        <Text
          fz={20}
          fw={700}
        >
          #{item.ot_id} {item.name}
        </Text>
        <Badge
          color='blue'
          variant='light'
        >
          {item.insumos?.length ?? 0} insumos
        </Badge>
      </Group>

      <Text
        fz='sm'
        c='dimmed'
      >
        Vencimiento:{' '}
        <Text
          span
          fw={500}
          c='bright'
        >
          {item.due_on?.split('T')[0] ?? '—'}
        </Text>
      </Text>

      <Text
        fz='sm'
        c='dimmed'
        mt={4}
      >
        Creado por:{' '}
        <Text
          span
          fw={500}
          c='bright'
        >
          {item.user?.name ?? '—'}
        </Text>
      </Text>

      <Group
        justify='flex-end'
        mt='md'
      >
        <Button
          size='xs'
          leftSection={loading ? <Loader size={14} /> : <IconPrinter size={14} />}
          variant='outline'
          onClick={openPdf}
          disabled={loading}
        >
          {loading ? 'Generando...' : 'Imprimir'}
        </Button>
      </Group>
    </Card>
  );
}
